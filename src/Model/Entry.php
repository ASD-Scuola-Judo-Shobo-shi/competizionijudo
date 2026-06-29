<?php

declare(strict_types=1);

namespace App\Model;

final class Entry
{
    public function __construct(
        public readonly int $id,
        public readonly int $event_id,
        public readonly int $club_id,
        public readonly int $athlete_id,
        public readonly string $created_at
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (int) ($data['event_id'] ?? 0),
            (int) ($data['club_id'] ?? 0),
            (int) ($data['athlete_id'] ?? 0),
            (string) ($data['created_at'] ?? '')
        );
    }

    /** @return list<array<string, mixed>> */
    public static function findByEvent(int $eventId, ?int $clubId): array
    {
        $sql = 'SELECT en.id AS entry_id, c.id AS club_id,
                c.name AS club_name, c.federal_code AS federal_code,
                CASE WHEN e.closed = 1 THEN en.snapshot_last_name ELSE a.last_name END AS last_name,
                CASE WHEN e.closed = 1 THEN en.snapshot_first_name ELSE a.first_name END AS first_name,
                CASE WHEN e.closed = 1 THEN en.snapshot_gender ELSE a.gender END AS gender,
                CASE WHEN e.closed = 1 THEN en.snapshot_weight_kg ELSE a.weight_kg END AS weight_kg,
                CASE WHEN e.closed = 1 THEN en.snapshot_belt ELSE a.belt END AS belt,
                CASE WHEN e.closed = 1 THEN en.snapshot_membership_number ELSE a.membership_number END AS membership_number,
                CASE WHEN e.closed = 1 THEN en.snapshot_date_of_birth ELSE a.date_of_birth END AS birth_date,
                CASE WHEN e.closed = 1 THEN en.snapshot_program ELSE \'\' END AS program,
                CASE WHEN e.closed = 1 THEN en.snapshot_weight_category ELSE \'\' END AS weight_category,
                e.name AS nome_evento, e.date AS data_gara, e.closed AS event_closed
            FROM entries en
            JOIN clubs c ON c.id = en.club_id
            JOIN athletes a ON a.id = en.athlete_id
            JOIN events e ON e.id = en.event_id
            WHERE en.event_id = ?';

        $params = [$eventId];
        if ($clubId !== null) {
            $sql .= ' AND c.id = ?';
            $params[] = $clubId;
        }

        $sql .= ' ORDER BY club_name, last_name, first_name, a.id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            if (empty($row['event_closed'])) {
                $category = JudoCategory::calculate(
                    (string) ($row['birth_date'] ?? ''),
                    (string) ($row['gender'] ?? ''),
                    (float) ($row['weight_kg'] ?? 0.0),
                    Athlete::eventYearFromDate((string) ($row['data_gara'] ?? ''))
                );
                $row['program'] = $category['program'];
                $row['weight_category'] = $category['weight_category'];
            }
            unset($row['event_closed']);
        }
        unset($row);

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public static function findClubsByEvent(int $eventId, ?int $clubId): array
    {
        $sql = 'SELECT DISTINCT c.id, c.name AS club_name, c.federal_code AS federal_code
                FROM entries en
                JOIN clubs c ON c.id = en.club_id
                WHERE en.event_id = ?';
        $params = [$eventId];

        if ($clubId !== null) {
            $sql .= ' AND c.id = ?';
            $params[] = $clubId;
        }

        $sql .= ' ORDER BY c.name';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array{id: int, name: string, date: string}> */
    public static function competitionsByClub(int $clubId, int $limit): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT e.id, e.name, e.date
             FROM entries en
             JOIN events e ON e.id = en.event_id
             WHERE en.club_id = ?
             ORDER BY e.date DESC, e.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $clubId, \PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn(array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'date' => (string) $row['date'],
            ],
            $stmt->fetchAll() ?: []
        );
    }

    /**
     * @param list<int> $athleteIds
     * @return array<int, int>
     */
    public static function registrationCountsByAthletes(
        int $clubId,
        array $athleteIds,
        ?int $eventId = null
    ): array {
        $athleteIds = array_values(array_unique(array_filter(
            array_map('intval', $athleteIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($athleteIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($athleteIds), '?'));
        $sql = 'SELECT athlete_id, COUNT(*) AS registrations
                FROM entries
                WHERE club_id = ? AND athlete_id IN (' . $placeholders . ')';
        $parameters = [$clubId, ...$athleteIds];
        if ($eventId !== null && $eventId > 0) {
            $sql .= ' AND event_id = ?';
            $parameters[] = $eventId;
        }
        $sql .= ' GROUP BY athlete_id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($parameters);
        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int) $row['athlete_id']] = (int) $row['registrations'];
        }

        return $counts;
    }

    /**
     * @param list<int> $eventIds
     * @return array<int, array{clubs: int, athletes: int}>
     */
    public static function countsByEventIds(array $eventIds): array
    {
        $eventIds = array_values(array_unique(array_filter(
            array_map('intval', $eventIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($eventIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($eventIds), '?'));
        $stmt = Database::connection()->prepare(
            'SELECT event_id, COUNT(DISTINCT club_id) AS clubs, COUNT(athlete_id) AS athletes
             FROM entries
             WHERE event_id IN (' . $placeholders . ')
             GROUP BY event_id'
        );
        $stmt->execute($eventIds);
        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int) $row['event_id']] = [
                'clubs' => (int) $row['clubs'],
                'athletes' => (int) $row['athletes'],
            ];
        }

        return $counts;
    }

    public static function register(
        int $eventId,
        int $clubId,
        int $athleteId,
        string $registrationDate
    ): EntryRegistrationResult {
        $repository = new EntryRegistrationRepository(Database::connection());

        return $repository->register($eventId, $clubId, $athleteId, $registrationDate);
    }

    /** @return list<int> */
    public static function findByClubEvent(int $eventId, int $clubId): array
    {
        $stmt = Database::connection()->prepare('SELECT athlete_id FROM entries WHERE event_id = ? AND club_id = ?');
        $stmt->execute([$eventId, $clubId]);

        return array_column($stmt->fetchAll(), 'athlete_id');
    }
}
