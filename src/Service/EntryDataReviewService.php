<?php

declare(strict_types=1);

namespace App\Service;

use App\Validation\EntryDataInput;
use PDO;
use Throwable;

final class EntryDataReviewService
{
    public function __construct(private readonly PDO $database)
    {
    }

    /**
     * @return array{entries:list<array<string, mixed>>, fingerprint:string, canAlign:bool}
     */
    public function alignmentPreview(int $eventId, bool $lock = false): array
    {
        $locking = $lock && $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $statement = $this->database->prepare('SELECT id, date, closed FROM events WHERE id = ?'
            . ($locking ? ' FOR UPDATE' : ''));
        $statement->execute([$eventId]);
        $event = $statement->fetch();
        if ($event === false) {
            throw new EntryDataReviewException('not_found');
        }
        $statement = $this->database->prepare(
            'SELECT en.*, en.id AS entry_id, c.name AS club_name, c.federal_code,
                    a.last_name, a.first_name, a.gender, a.birth_date, a.weight_kg, a.belt, a.membership_number,
                    (SELECT review.id FROM entry_data_reviews review
                     WHERE review.entry_id = en.id AND review.status = \'approved\'
                     ORDER BY review.id DESC LIMIT 1) AS last_review_id
             FROM entries en JOIN athletes a ON a.id = en.athlete_id AND a.club_id = en.club_id
             JOIN clubs c ON c.id = en.club_id
             WHERE en.event_id = ? ORDER BY en.id' . ($locking ? ' FOR UPDATE' : '')
        );
        $statement->execute([$eventId]);
        $entries = $statement->fetchAll();
        foreach ($entries as &$entry) {
            $entry['event_date'] = $event['date'];
            $entry['closed'] = $event['closed'];
        }
        unset($entry);

        return [
            'entries' => $entries,
            'fingerprint' => hash('sha256', TournamentData::json(['event' => $event, 'entries' => $entries])),
            'canAlign' => TournamentData::now() < TournamentData::cutoff((string) $event['date']),
        ];
    }

    public function alignCurrentProfiles(int $eventId, string $fingerprint, string $reason): int
    {
        $count = 0;
        $this->transaction(function () use ($eventId, $fingerprint, $reason, &$count): void {
            $preview = $this->alignmentPreview($eventId, true);
            if (!$preview['canAlign']) {
                throw new EntryDataReviewException('cutoff');
            }
            if (!hash_equals($preview['fingerprint'], $fingerprint)) {
                throw new EntryDataReviewException('stale');
            }
            $this->validateReason($reason);
            foreach ($preview['entries'] as $entry) {
                if (empty($entry['closed']) && $entry['snapshot_at'] === null) {
                    continue;
                }
                $proposed = TournamentData::categorize($entry, (string) $entry['event_date']);
                $current = $this->current($entry);
                $input = array_map(static fn(?string $value): string => $value ?? '', $proposed);
                if ($input === array_map(static fn(?string $value): string => $value ?? '', $current)) {
                    continue;
                }
                if (EntryDataInput::errors($input) !== []) {
                    throw new EntryDataReviewException('invalid');
                }
                $reviewId = $this->createReview(
                    $entry,
                    $proposed,
                    $reason,
                    $this->fingerprint($entry, $current),
                    TournamentData::now(),
                    null,
                    null
                );
                $this->apply($entry, $proposed);
                $this->recordDecision($reviewId, 'approved', $reason);
                $count++;
            }
        });

        return $count;
    }

    /** @return array<string, mixed> */
    public function details(int $eventId, int $entryId, ?int $clubId): array
    {
        $entry = $this->entry($eventId, $entryId, $clubId);
        $current = $this->current($entry);
        $statement = $this->database->prepare(
            'SELECT * FROM athlete_revisions WHERE athlete_id = ? AND club_id = ? AND recorded_at < ?
             ORDER BY recorded_at DESC, id DESC LIMIT 50'
        );
        $statement->execute([
            $entry['athlete_id'], $entry['club_id'], TournamentData::cutoff((string) $entry['event_date']),
        ]);
        $revisions = [];
        foreach ($statement->fetchAll() as $revision) {
            $revision['data'] = TournamentData::categorize($revision, (string) $entry['event_date']);
            $revisions[] = $revision;
        }
        $statement = $this->database->prepare(
            'SELECT * FROM entry_data_reviews WHERE entry_id = ? ORDER BY id DESC LIMIT 50'
        );
        $statement->execute([$entryId]);
        $history = $statement->fetchAll();
        foreach ($history as &$review) {
            $review['before'] = json_decode((string) $review['before_data'], true, 512, JSON_THROW_ON_ERROR);
            $review['proposed'] = json_decode((string) $review['proposed_data'], true, 512, JSON_THROW_ON_ERROR);
        }
        unset($review);

        return [
            'entry' => $entry,
            'current' => $current,
            'fingerprint' => $this->fingerprint($entry, $current),
            'revisions' => $revisions,
            'history' => $history,
            'canEdit' => TournamentData::now() < TournamentData::cutoff((string) $entry['event_date']),
        ];
    }

    public function requestRevision(
        int $eventId,
        int $entryId,
        int $revisionId,
        ?int $clubId,
        string $reason,
        string $fingerprint,
        bool $approve = false
    ): void {
        if ($approve && $clubId !== null) {
            throw new EntryDataReviewException('invalid');
        }
        $this->transaction(function () use (
            $eventId,
            $entryId,
            $revisionId,
            $clubId,
            $reason,
            $fingerprint,
            $approve
        ): void {
            $entry = $this->entry($eventId, $entryId, $clubId, true);
            if (
                $clubId !== null && empty($entry['closed']) && empty($entry['snapshot_at'])
                && TournamentData::now() < TournamentData::cutoff((string) $entry['event_date'])
            ) {
                throw new EntryDataReviewException('live');
            }
            $revision = $this->revision($revisionId, $entry);
            $proposed = TournamentData::categorize($revision, (string) $entry['event_date']);
            $reviewId = $this->createReview(
                $entry,
                $proposed,
                $reason,
                $fingerprint,
                (string) $revision['recorded_at'],
                $revisionId,
                $clubId
            );
            if ($approve) {
                $this->apply($entry, $proposed);
                $this->recordDecision($reviewId, 'approved', $reason);
            }
        });
    }

    /** @param array<string, mixed> $data */
    public function edit(
        int $eventId,
        int $entryId,
        array $data,
        string $reason,
        string $fingerprint
    ): void {
        $this->transaction(function () use ($eventId, $entryId, $data, $reason, $fingerprint): void {
            $entry = $this->entry($eventId, $entryId, null, true);
            $now = TournamentData::now();
            if ($now >= TournamentData::cutoff((string) $entry['event_date'])) {
                throw new EntryDataReviewException('cutoff');
            }
            $proposed = TournamentData::categorize($data, (string) $entry['event_date']);
            $reviewId = $this->createReview($entry, $proposed, $reason, $fingerprint, $now, null, null);
            $this->apply($entry, $proposed);
            $this->recordDecision($reviewId, 'approved', $reason);
        });
    }

    public function decide(int $eventId, int $entryId, int $reviewId, bool $approve, string $reason): void
    {
        $this->transaction(function () use ($eventId, $entryId, $reviewId, $approve, $reason): void {
            $entry = $this->entry($eventId, $entryId, null, true);
            $this->validateReason($reason);
            $statement = $this->database->prepare('SELECT * FROM entry_data_reviews WHERE id = ? AND entry_id = ?');
            $statement->execute([$reviewId, $entryId]);
            $review = $statement->fetch();
            if ($review === false || $review['status'] !== 'pending') {
                throw new EntryDataReviewException('not_pending');
            }
            if ($approve) {
                if ((string) $review['source_recorded_at'] >= TournamentData::cutoff((string) $entry['event_date'])) {
                    throw new EntryDataReviewException('cutoff');
                }
                $this->revision((int) $review['revision_id'], $entry);
                $this->assertFresh($entry, (string) $review['base_fingerprint']);
                $proposed = json_decode((string) $review['proposed_data'], true, 512, JSON_THROW_ON_ERROR);
                $this->apply($entry, TournamentData::normalize($proposed));
            }
            $this->recordDecision($reviewId, $approve ? 'approved' : 'rejected', $reason);
        });
    }

    /** @return list<array<string, mixed>> */
    public function pending(int $eventId): array
    {
        $statement = $this->database->prepare(
            'SELECT r.id, r.entry_id, r.requested_at, r.reason, c.name AS club_name
             FROM entry_data_reviews r JOIN entries en ON en.id = r.entry_id
             JOIN clubs c ON c.id = en.club_id
             WHERE en.event_id = ? AND r.status = ? ORDER BY r.id LIMIT 100'
        );
        $statement->execute([$eventId, 'pending']);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed> */
    private function entry(int $eventId, int $entryId, ?int $clubId, bool $lock = false): array
    {
        $locking = $lock && $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($locking) {
            // Match registration/closure lock order, then read the latest committed review version.
            $statement = $this->database->prepare('SELECT id FROM events WHERE id = ? FOR UPDATE');
            $statement->execute([$eventId]);
        }
        $sql = 'SELECT en.*, e.date AS event_date, e.name AS event_name, e.closed, e.published,
                    a.last_name, a.first_name, a.gender, a.birth_date, a.weight_kg, a.belt, a.membership_number
                FROM entries en JOIN events e ON e.id = en.event_id
                JOIN athletes a ON a.id = en.athlete_id AND a.club_id = en.club_id
                WHERE en.id = ? AND en.event_id = ?';
        $parameters = [$entryId, $eventId];
        if ($clubId !== null) {
            $sql .= ' AND en.club_id = ? AND e.published = 1';
            $parameters[] = $clubId;
        }
        if ($locking) {
            $sql .= ' FOR UPDATE';
        }
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
        $entry = $statement->fetch();
        if ($entry === false) {
            throw new EntryDataReviewException('not_found');
        }
        $statement = $this->database->prepare(
            'SELECT id FROM entry_data_reviews WHERE entry_id = ? AND status = ? ORDER BY id DESC LIMIT 1'
            . ($locking ? ' FOR UPDATE' : '')
        );
        $statement->execute([$entryId, 'approved']);
        $entry['last_review_id'] = $statement->fetchColumn() ?: null;

        return $entry;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, string|null>
     */
    private function current(array $entry): array
    {
        if (!empty($entry['closed']) || $entry['snapshot_at'] !== null) {
            $row = [];
            foreach (TournamentData::FIELDS as $field) {
                $row[$field] = $entry['snapshot_' . ($field === 'type' ? 'program' : $field)] ?? null;
            }

            return TournamentData::normalize($row);
        }
        if (TournamentData::now() >= TournamentData::cutoff((string) $entry['event_date'])) {
            $statement = $this->database->prepare(
                'SELECT * FROM athlete_revisions WHERE athlete_id = ? AND club_id = ? AND recorded_at < ?
                 ORDER BY recorded_at DESC, id DESC LIMIT 1'
            );
            $statement->execute([
                $entry['athlete_id'], $entry['club_id'], TournamentData::cutoff((string) $entry['event_date']),
            ]);
            $revision = $statement->fetch();

            return $revision === false ? TournamentData::normalize([])
                : TournamentData::categorize($revision, (string) $entry['event_date']);
        }

        return TournamentData::categorize($entry, (string) $entry['event_date']);
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function revision(int $revisionId, array $entry): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM athlete_revisions WHERE id = ? AND athlete_id = ? AND club_id = ? AND recorded_at < ?'
        );
        $statement->execute([
            $revisionId, $entry['athlete_id'], $entry['club_id'], TournamentData::cutoff((string) $entry['event_date']),
        ]);
        $revision = $statement->fetch();
        if ($revision === false) {
            throw new EntryDataReviewException('revision');
        }

        return $revision;
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, string|null> $proposed
     */
    private function createReview(
        array $entry,
        array $proposed,
        string $reason,
        string $fingerprint,
        string $recordedAt,
        ?int $revisionId,
        ?int $clubId
    ): int {
        $this->validateReason($reason);
        $current = $this->current($entry);
        $this->assertFresh($entry, $fingerprint);
        if ($proposed === $current) {
            throw new EntryDataReviewException('unchanged');
        }
        $statement = $this->database->prepare(
            'SELECT id FROM entry_data_reviews WHERE entry_id = ? AND status = ? LIMIT 1'
        );
        $statement->execute([$entry['id'], 'pending']);
        if ($clubId !== null && $statement->fetchColumn() !== false) {
            throw new EntryDataReviewException('pending');
        }
        $statement = $this->database->prepare(
            'INSERT INTO entry_data_reviews
             (entry_id, revision_id, requester_club_id, reason, before_data, proposed_data, base_fingerprint,
              source_recorded_at, requested_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $entry['id'], $revisionId, $clubId, trim($reason), TournamentData::json($current),
            TournamentData::json($proposed), $fingerprint, $recordedAt, TournamentData::now(),
        ]);

        return (int) $this->database->lastInsertId();
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, string|null> $data
     */
    private function fingerprint(array $entry, array $data): string
    {
        return hash('sha256', TournamentData::json([
            'data' => $data, 'athlete_id' => (int) $entry['athlete_id'], 'closed' => (int) $entry['closed'],
            'date' => $entry['event_date'], 'last_review_id' => $entry['last_review_id'],
        ]));
    }

    /** @param array<string, mixed> $entry */
    private function assertFresh(array $entry, string $fingerprint): void
    {
        if (!hash_equals($this->fingerprint($entry, $this->current($entry)), $fingerprint)) {
            throw new EntryDataReviewException('stale');
        }
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, string|null> $data
     */
    private function apply(array $entry, array $data): void
    {
        $assignments = [];
        $parameters = [];
        foreach (TournamentData::FIELDS as $field) {
            $assignments[] = 'snapshot_' . ($field === 'type' ? 'program' : $field) . ' = ?';
            $parameters[] = $data[$field];
        }
        $parameters[] = !empty($entry['closed'])
            ? (string) $entry['event_date'] . ' 00:00:00'
            : TournamentData::now();
        $parameters[] = $entry['id'];
        $statement = $this->database->prepare(
            'UPDATE entries SET ' . implode(', ', $assignments)
            . ' , snapshot_at = COALESCE(snapshot_at, ?) WHERE id = ?'
        );
        $statement->execute($parameters);
    }

    private function recordDecision(int $reviewId, string $status, string $reason): void
    {
        $statement = $this->database->prepare(
            'UPDATE entry_data_reviews SET status = ?, decided_at = ?, decision_reason = ?, decision_actor = ?
             WHERE id = ? AND status = ?'
        );
        $statement->execute([$status, TournamentData::now(), trim($reason), 'administrator', $reviewId, 'pending']);
    }

    private function validateReason(string $reason): void
    {
        if (trim($reason) === '' || mb_strlen($reason) > 1000) {
            throw new EntryDataReviewException('reason');
        }
    }

    private function transaction(callable $operation): void
    {
        $this->database->beginTransaction();
        try {
            $operation();
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }
}
