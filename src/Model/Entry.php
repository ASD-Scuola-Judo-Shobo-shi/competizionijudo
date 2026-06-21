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
    public static function findByEvent(int $eventId, int $clubId = 0): array
    {
        $sql = 'SELECT en.id AS entry_id, c.id AS club_id, c.name AS club_name, c.federal_code AS federal_code, a.last_name AS last_name, a.first_name AS first_name, a.weight_kg AS weight_kg, a.weight_category AS weight_category, a.belt AS belt, a.membership_number AS membership_number, a.date_of_birth AS birth_date, e.name AS nome_evento, e.date AS data_gara
            FROM entries en
            JOIN clubs c ON c.id = en.club_id
            JOIN athletes a ON a.id = en.athlete_id
            JOIN events e ON e.id = en.event_id
            WHERE en.event_id = ?';

        $params = [$eventId];
        if ($clubId > 0) {
            $sql .= ' AND c.id = ?';
            $params[] = $clubId;
        }

        $sql .= ' ORDER BY c.name, a.last_name, a.first_name, a.id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function findClubsByEvent(int $eventId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT c.id, c.name AS club_name, c.federal_code AS federal_code
             FROM entries en
             JOIN clubs c ON c.id = en.club_id
             WHERE en.event_id = ?
               ORDER BY c.name'
        );
        $stmt->execute([$eventId]);

        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function findByClub(int $clubId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT en.*, e.name AS nome_evento, e.date AS data_gara, a.last_name AS last_name, a.first_name AS first_name, a.date_of_birth AS birth_date, a.weight_kg AS weight_kg, a.weight_category AS weight_category
             FROM entries en
             JOIN events e ON e.id = en.event_id
             JOIN athletes a ON a.id = en.athlete_id
             WHERE en.club_id = ?
             ORDER BY e.date DESC'
        );
        $stmt->execute([$clubId]);

        return $stmt->fetchAll() ?: [];
    }

    public static function register(int $eventId, int $clubId, int $athleteId): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT IGNORE INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        );
        $stmt->execute([$eventId, $clubId, $athleteId]);
    }

    /** @return list<int> */
    public static function findByClubEvent(int $eventId, int $clubId): array
    {
        $stmt = Database::connection()->prepare('SELECT athlete_id FROM entries WHERE event_id = ? AND club_id = ?');
        $stmt->execute([$eventId, $clubId]);

        return array_column($stmt->fetchAll(), 'athlete_id');
    }
}
