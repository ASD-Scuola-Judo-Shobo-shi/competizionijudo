<?php

declare(strict_types=1);

namespace App\Model;

use App\Service\TournamentData;
use PDO;
use RuntimeException;

final class EntrySnapshotService
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function consolidate(int $eventId, string $eventDate): void
    {
        $ended = TournamentData::now() >= TournamentData::cutoff($eventDate);
        $source = $ended ? 'r' : 'a';
        $revisionJoin = $ended
            ? 'LEFT JOIN athlete_revisions r ON r.id = (
                SELECT revision.id FROM athlete_revisions revision
                WHERE revision.athlete_id = en.athlete_id AND revision.club_id = en.club_id
                  AND revision.recorded_at < ?
                ORDER BY revision.recorded_at DESC, revision.id DESC LIMIT 1
            )' : '';
        $entries = $this->database->prepare(
            'SELECT en.id, ' . $source . '.last_name, ' . $source . '.first_name, '
                . $source . '.gender, ' . $source . '.birth_date, '
                . $source . '.weight_kg, ' . $source . '.belt, ' . $source . '.membership_number
             FROM entries en
             JOIN athletes a ON a.id = en.athlete_id ' . $revisionJoin . '
             WHERE en.event_id = ? AND en.snapshot_at IS NULL ORDER BY en.id'
        );
        $entries->execute($ended ? [TournamentData::cutoff($eventDate), $eventId] : [$eventId]);
        $update = $this->database->prepare(
            'UPDATE entries
             SET snapshot_last_name = ?, snapshot_first_name = ?, snapshot_gender = ?,
                 snapshot_birth_date = ?, snapshot_weight_kg = ?, snapshot_belt = ?,
                 snapshot_membership_number = ?, snapshot_program = ?,
                 snapshot_weight_category = ?, snapshot_at = CURRENT_TIMESTAMP
             WHERE id = ? AND event_id = ?'
        );

        foreach ($entries->fetchAll() as $entry) {
            $category = JudoCategory::calculate(
                (string) $entry['birth_date'],
                (string) $entry['gender'],
                (float) $entry['weight_kg'],
                Athlete::eventYearFromDate($eventDate)
            );
            $update->execute([
                $entry['last_name'],
                $entry['first_name'],
                $entry['gender'],
                $entry['birth_date'],
                $entry['weight_kg'],
                $entry['belt'],
                $entry['membership_number'],
                $entry['birth_date'] !== null ? $category['type'] : null,
                $entry['birth_date'] !== null ? $category['weight_category'] : null,
                $entry['id'],
                $eventId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Unable to consolidate an event entry snapshot.');
            }
        }
    }

    /**
     * Snapshot one entry that was registered after an event was closed.
     *
     * Returns false when the event is open, so registrations allowed by an
     * exception do not acquire a snapshot before the event is closed.
     */
    public function consolidateClosedEntry(int $eventId, int $clubId, int $athleteId): bool
    {
        $entryStatement = $this->database->prepare(
            'SELECT en.id, a.last_name, a.first_name, a.gender, a.birth_date,
                    a.weight_kg, a.belt, a.membership_number, e.date AS event_date
             FROM entries en
             JOIN athletes a ON a.id = en.athlete_id
             JOIN events e ON e.id = en.event_id
             WHERE en.event_id = ? AND en.club_id = ? AND en.athlete_id = ? AND e.closed = 1
               AND en.snapshot_at IS NULL'
        );
        $entryStatement->execute([$eventId, $clubId, $athleteId]);
        $entry = $entryStatement->fetch();
        if ($entry === false) {
            return false;
        }
        if (TournamentData::now() >= TournamentData::cutoff((string) $entry['event_date'])) {
            throw new RuntimeException('An ended event cannot snapshot newly registered athlete data.');
        }

        $category = JudoCategory::calculate(
            (string) $entry['birth_date'],
            (string) $entry['gender'],
            (float) $entry['weight_kg'],
            Athlete::eventYearFromDate((string) $entry['event_date'])
        );
        $update = $this->database->prepare(
            'UPDATE entries
             SET snapshot_last_name = ?, snapshot_first_name = ?, snapshot_gender = ?,
                 snapshot_birth_date = ?, snapshot_weight_kg = ?, snapshot_belt = ?,
                 snapshot_membership_number = ?, snapshot_program = ?,
                 snapshot_weight_category = ?, snapshot_at = CURRENT_TIMESTAMP
             WHERE id = ? AND event_id = ?'
        );
        $update->execute([
            $entry['last_name'],
            $entry['first_name'],
            $entry['gender'],
            $entry['birth_date'],
            $entry['weight_kg'],
            $entry['belt'],
            $entry['membership_number'],
            $category['type'],
            $category['weight_category'],
            $entry['id'],
            $eventId,
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Unable to consolidate an event entry snapshot.');
        }

        return true;
    }
}
