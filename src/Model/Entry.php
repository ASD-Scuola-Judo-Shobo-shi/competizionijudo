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
        $sql = 'SELECT en.id AS entry_id, c.id AS club_id, c.name AS club_name, c.federal_code AS federal_code, a.last_name AS last_name, a.first_name AS first_name, a.weight_kg AS weight_kg, a.weight_category AS weight_category, a.belt AS belt, a.membership_number AS membership_number, a.date_of_birth AS birth_date, e.name AS nome_evento, e.date AS data_gara
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

        $sql .= ' ORDER BY c.name, a.last_name, a.first_name, a.id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
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
