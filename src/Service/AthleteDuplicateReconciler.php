<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

final class AthleteDuplicateReconciler
{
    private readonly AthleteFieldReconciler $fieldReconciler;

    public function __construct(
        private readonly PDO $database,
        ?AthleteFieldReconciler $fieldReconciler = null
    ) {
        $this->fieldReconciler = $fieldReconciler ?? new AthleteFieldReconciler();
    }

    public function run(bool $apply = false, ?int $clubId = null): AthleteDuplicateCleanupResult
    {
        if ($clubId !== null && $clubId <= 0) {
            throw new RuntimeException('The club ID must be a positive integer.');
        }

        $ownsTransaction = $apply && !$this->database->inTransaction();
        if ($ownsTransaction) {
            $this->database->beginTransaction();
        }

        try {
            $athletes = $this->athletes($clubId, $apply);
            [$identityGroups, $nameGroups] = $this->groups($athletes);
            $groups = [];
            $blockedGroups = [];
            $survivorIds = [];

            foreach ($identityGroups as $athleteGroup) {
                if (count($athleteGroup) < 2) {
                    continue;
                }

                $entries = $this->entriesForAthletes(
                    array_column($athleteGroup, 'id'),
                    $apply
                );
                $blockedReason = $this->blockedReason($athleteGroup, $entries);
                if ($blockedReason !== null) {
                    $blockedGroups[] = [
                        'club_id' => $athleteGroup[0]['club_id'],
                        'athlete_ids' => array_column($athleteGroup, 'id'),
                        'last_name' => $this->fieldReconciler->titleCaseName(
                            $athleteGroup[0]['last_name']
                        ),
                        'first_name' => $this->fieldReconciler->titleCaseName(
                            $athleteGroup[0]['first_name']
                        ),
                        'birth_date' => $athleteGroup[0]['birth_date'],
                        'reason' => $blockedReason['reason'],
                        'overlapping_event_ids' => $blockedReason['event_ids'],
                    ];
                    continue;
                }

                [$finalData, $resolutions] = $this->reconciledData($athleteGroup);
                $survivorId = $athleteGroup[0]['id'];
                $duplicateIds = array_column(array_slice($athleteGroup, 1), 'id');
                foreach ($duplicateIds as $duplicateId) {
                    $survivorIds[$duplicateId] = $survivorId;
                }
                $entryMoves = count(array_filter(
                    $entries,
                    static fn(array $entry): bool => $entry['athlete_id'] !== $survivorId
                ));

                if ($apply) {
                    $this->applyGroup(
                        $athleteGroup[0]['club_id'],
                        $survivorId,
                        $duplicateIds,
                        $finalData,
                        $entryMoves
                    );
                }

                $groups[] = [
                    'club_id' => $athleteGroup[0]['club_id'],
                    'survivor_id' => $survivorId,
                    'duplicate_ids' => $duplicateIds,
                    'last_name' => $finalData['last_name'],
                    'first_name' => $finalData['first_name'],
                    'birth_date' => $finalData['birth_date'],
                    'entry_moves' => $entryMoves,
                    'resolutions' => $resolutions,
                ];
            }

            if ($ownsTransaction && !$this->database->commit()) {
                throw new RuntimeException('Unable to commit athlete duplicate cleanup.');
            }

            return new AthleteDuplicateCleanupResult(
                $apply,
                $groups,
                $blockedGroups,
                $this->nameCollisions($nameGroups, $survivorIds)
            );
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return list<array{
     *     id: int,
     *     club_id: int,
     *     last_name: string,
     *     first_name: string,
     *     gender: string,
     *     birth_date: string,
     *     weight_kg: float|null,
     *     belt: string,
     *     membership_number: string|null,
     *     notes: string|null
     * }>
     */
    private function athletes(?int $clubId, bool $lock): array
    {
        $sql = 'SELECT id, club_id, last_name, first_name, gender, birth_date, weight_kg,
                       belt, membership_number, notes
                FROM athletes';
        $parameters = [];
        if ($clubId !== null) {
            $sql .= ' WHERE club_id = ?';
            $parameters[] = $clubId;
        }
        $sql .= ' ORDER BY club_id ASC, id ASC' . $this->lockClause($lock);

        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'club_id' => (int) $row['club_id'],
                'last_name' => (string) $row['last_name'],
                'first_name' => (string) $row['first_name'],
                'gender' => (string) $row['gender'],
                'birth_date' => (string) $row['birth_date'],
                'weight_kg' => $row['weight_kg'] !== null ? (float) $row['weight_kg'] : null,
                'belt' => (string) ($row['belt'] ?? ''),
                'membership_number' => $row['membership_number'] !== null
                    ? (string) $row['membership_number']
                    : null,
                'notes' => $row['notes'] !== null ? (string) $row['notes'] : null,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array{
     *     id: int,
     *     club_id: int,
     *     last_name: string,
     *     first_name: string,
     *     gender: string,
     *     birth_date: string,
     *     weight_kg: float|null,
     *     belt: string,
     *     membership_number: string|null,
     *     notes: string|null
     * }> $athletes
     * @return array{0: array<string, list<array<string, mixed>>>, 1: array<string, list<array<string, mixed>>>}
     */
    private function groups(array $athletes): array
    {
        $byIdentity = [];
        $byName = [];
        foreach ($athletes as $athlete) {
            $name = $this->nameKey($athlete);
            if ($name === null) {
                continue;
            }
            $byName[$athlete['club_id'] . "\0" . $name][] = $athlete;
            if ($athlete['birth_date'] !== '') {
                $byIdentity[
                    $athlete['club_id'] . "\0" . $name . "\0" . $athlete['birth_date']
                ][] = $athlete;
            }
        }

        return [$byIdentity, $byName];
    }

    /**
     * @param list<int> $athleteIds
     * @return list<array{id: int, event_id: int, club_id: int, athlete_id: int}>
     */
    private function entriesForAthletes(array $athleteIds, bool $lock): array
    {
        $placeholders = implode(',', array_fill(0, count($athleteIds), '?'));
        $statement = $this->database->prepare(
            'SELECT id, event_id, club_id, athlete_id
             FROM entries
             WHERE athlete_id IN (' . $placeholders . ')
             ORDER BY event_id ASC, club_id ASC, id ASC' . $this->lockClause($lock)
        );
        $statement->execute($athleteIds);
        $entries = [];
        foreach ($statement->fetchAll() as $entry) {
            $entries[] = [
                'id' => (int) $entry['id'],
                'event_id' => (int) $entry['event_id'],
                'club_id' => (int) $entry['club_id'],
                'athlete_id' => (int) $entry['athlete_id'],
            ];
        }

        return $entries;
    }

    /**
     * @param list<array<string, mixed>> $athletes
     * @param list<array{id: int, event_id: int, club_id: int, athlete_id: int}> $entries
     * @return array{reason: string, event_ids: list<int>}|null
     */
    private function blockedReason(array $athletes, array $entries): ?array
    {
        $clubId = (int) $athletes[0]['club_id'];
        $eventAthletes = [];
        foreach ($entries as $entry) {
            if ($entry['club_id'] !== $clubId) {
                return ['reason' => 'entry_club_mismatch', 'event_ids' => [$entry['event_id']]];
            }
            $eventAthletes[$entry['event_id']][$entry['athlete_id']] = true;
        }

        $overlappingEventIds = [];
        foreach ($eventAthletes as $eventId => $athleteIds) {
            if (count($athleteIds) > 1) {
                $overlappingEventIds[] = (int) $eventId;
            }
        }
        sort($overlappingEventIds);

        return $overlappingEventIds !== []
            ? ['reason' => 'overlapping_entries', 'event_ids' => $overlappingEventIds]
            : null;
    }

    /**
     * @param list<array<string, mixed>> $athletes
     * @return array{
     *     0: array{
     *         last_name: string,
     *         first_name: string,
     *         gender: string,
     *         birth_date: string,
     *         weight_kg: float|null,
     *         belt: string,
     *         membership_number: string|null,
     *         notes: string|null
     *     },
     *     1: array<int, array<string, string>>
     * }
     */
    private function reconciledData(array $athletes): array
    {
        $database = $this->databaseData($athletes[0]);
        $resolutions = [];
        foreach (array_slice($athletes, 1) as $duplicate) {
            [$row, $duplicateResolutions] = $this->fieldReconciler->reconcile(
                $database,
                $this->importData($duplicate)
            );
            $database = $this->databaseData($row);
            $resolutions[(int) $duplicate['id']] = $duplicateResolutions;
        }

        return [$database, $resolutions];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{
     *     last_name: string,
     *     first_name: string,
     *     gender: string,
     *     birth_date: string,
     *     weight_kg: float|null,
     *     belt: string,
     *     membership_number: string|null,
     *     notes: string|null
     * }
     */
    private function databaseData(array $row): array
    {
        $weight = $row['weight_kg'] ?? null;
        $membership = trim((string) ($row['membership_number'] ?? ''));
        $notes = trim((string) ($row['notes'] ?? ''));

        return [
            'last_name' => (string) ($row['last_name'] ?? ''),
            'first_name' => (string) ($row['first_name'] ?? ''),
            'gender' => (string) ($row['gender'] ?? ''),
            'birth_date' => (string) ($row['birth_date'] ?? ''),
            'weight_kg' => $weight !== null && $weight !== '' ? (float) $weight : null,
            'belt' => (string) ($row['belt'] ?? ''),
            'membership_number' => $membership !== '' ? $membership : null,
            'notes' => $notes !== '' ? $notes : null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    private function importData(array $row): array
    {
        return [
            'last_name' => $this->fieldReconciler->titleCaseName(
                (string) ($row['last_name'] ?? '')
            ),
            'first_name' => $this->fieldReconciler->titleCaseName(
                (string) ($row['first_name'] ?? '')
            ),
            'gender' => trim((string) ($row['gender'] ?? '')),
            'birth_date' => trim((string) ($row['birth_date'] ?? '')),
            'weight_kg' => $this->fieldReconciler->formatWeight(
                isset($row['weight_kg']) ? (float) $row['weight_kg'] : null
            ),
            'belt' => trim((string) ($row['belt'] ?? '')),
            'membership_number' => $this->cleanText(
                (string) ($row['membership_number'] ?? '')
            ),
            'notes' => trim((string) ($row['notes'] ?? '')),
        ];
    }

    /**
     * @param list<int> $duplicateIds
     * @param array<string, mixed> $finalData
     */
    private function applyGroup(
        int $clubId,
        int $survivorId,
        array $duplicateIds,
        array $finalData,
        int $expectedEntryMoves
    ): void {
        $updateAthlete = $this->database->prepare(
            'UPDATE athletes
             SET last_name = ?, first_name = ?, gender = ?, birth_date = ?, weight_kg = ?,
                 belt = ?, membership_number = ?, notes = ?
             WHERE id = ? AND club_id = ?'
        );
        $updateAthlete->execute([
            $finalData['last_name'],
            $finalData['first_name'],
            $finalData['gender'],
            $finalData['birth_date'],
            $finalData['weight_kg'],
            $finalData['belt'] !== '' ? $finalData['belt'] : null,
            $finalData['membership_number'],
            $finalData['notes'],
            $survivorId,
            $clubId,
        ]);

        $placeholders = implode(',', array_fill(0, count($duplicateIds), '?'));
        $moveEntries = $this->database->prepare(
            'UPDATE entries SET athlete_id = ? WHERE athlete_id IN (' . $placeholders . ')'
        );
        $moveEntries->execute([$survivorId, ...$duplicateIds]);
        if ($moveEntries->rowCount() !== $expectedEntryMoves) {
            throw new RuntimeException('The number of reassigned entries changed during cleanup.');
        }

        $deleteDuplicates = $this->database->prepare(
            'DELETE FROM athletes
             WHERE club_id = ? AND id IN (' . $placeholders . ')'
        );
        $deleteDuplicates->execute([$clubId, ...$duplicateIds]);
        if ($deleteDuplicates->rowCount() !== count($duplicateIds)) {
            throw new RuntimeException('The number of duplicate athletes changed during cleanup.');
        }
    }

    /**
     * @param array<string, list<array<string, mixed>>> $nameGroups
     * @param array<int, int> $survivorIds
     * @return list<array{
     *     club_id: int,
     *     athlete_ids: list<int>,
     *     last_name: string,
     *     first_name: string,
     *     birth_dates: list<string>
     * }>
     */
    private function nameCollisions(array $nameGroups, array $survivorIds): array
    {
        $collisions = [];
        foreach ($nameGroups as $athletes) {
            $remainingAthletes = [];
            foreach ($athletes as $athlete) {
                $athleteId = (int) $athlete['id'];
                $remainingAthletes[$survivorIds[$athleteId] ?? $athleteId] = (string) $athlete['birth_date'];
            }
            $birthDates = array_values(array_unique($remainingAthletes));
            sort($birthDates);
            if (count($birthDates) < 2) {
                continue;
            }
            $collisions[] = [
                'club_id' => (int) $athletes[0]['club_id'],
                'athlete_ids' => array_keys($remainingAthletes),
                'last_name' => $this->fieldReconciler->titleCaseName(
                    (string) $athletes[0]['last_name']
                ),
                'first_name' => $this->fieldReconciler->titleCaseName(
                    (string) $athletes[0]['first_name']
                ),
                'birth_dates' => array_map('strval', $birthDates),
            ];
        }

        return $collisions;
    }

    /** @param array<string, mixed> $athlete */
    private function nameKey(array $athlete): ?string
    {
        $lastName = mb_strtolower($this->cleanText((string) $athlete['last_name']), 'UTF-8');
        $firstName = mb_strtolower($this->cleanText((string) $athlete['first_name']), 'UTF-8');

        return $lastName !== '' && $firstName !== ''
            ? $lastName . "\0" . $firstName
            : null;
    }

    private function cleanText(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function lockClause(bool $lock): string
    {
        return $lock && $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ' FOR UPDATE'
            : '';
    }
}
