<?php

declare(strict_types=1);

namespace App\Model;

final class EventRegistrationException
{
    public function __construct(
        public readonly int $id,
        public readonly int $event_id,
        public readonly int $club_id,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (int) ($data['event_id'] ?? 0),
            (int) ($data['club_id'] ?? 0),
        );
    }

    /**
     * @param list<int> $clubIds
     */
    public static function setForEvent(int $eventId, array $clubIds): void
    {
        $db = Database::connection();

        // Remove all existing exceptions for this event
        $stmt = $db->prepare('DELETE FROM event_registration_exceptions WHERE event_id = ?');
        $stmt->execute([$eventId]);

        // Insert new exceptions
        $stmt = $db->prepare('INSERT INTO event_registration_exceptions (event_id, club_id) VALUES (?, ?)');
        foreach ($clubIds as $clubId) {
            $stmt->execute([$eventId, $clubId]);
        }
    }

    /**
     * @return list<int>
     */
    public static function clubIdsForEvent(int $eventId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT club_id FROM event_registration_exceptions WHERE event_id = ? ORDER BY club_id'
        );
        $stmt->execute([$eventId]);

        return array_column($stmt->fetchAll(), 'club_id');
    }

    public static function exists(int $eventId, int $clubId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM event_registration_exceptions WHERE event_id = ? AND club_id = ?'
        );
        $stmt->execute([$eventId, $clubId]);

        return (bool) $stmt->fetch();
    }

    public static function remove(int $eventId, int $clubId): void
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM event_registration_exceptions WHERE event_id = ? AND club_id = ?'
        );
        $stmt->execute([$eventId, $clubId]);
    }
}
