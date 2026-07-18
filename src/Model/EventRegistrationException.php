<?php

declare(strict_types=1);

namespace App\Model;

final class EventRegistrationException
{
    /**
     * @return list<int> Returns list of club_ids that have exceptions for the given event
     */
    public static function findClubIdsByEvent(int $eventId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT club_id FROM event_registration_exceptions WHERE event_id = ?'
        );
        $stmt->execute([$eventId]);

        return array_column($stmt->fetchAll() ?: [], 'club_id');
    }

    /**
     * @return list<array{id: int, name: string, exception_id: int}>
     */
    public static function findClubsWithExceptions(int $eventId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.id, c.name, ere.id AS exception_id
             FROM event_registration_exceptions ere
             JOIN clubs c ON c.id = ere.club_id
             WHERE ere.event_id = ?
             ORDER BY c.name'
        );
        $stmt->execute([$eventId]);

        return $stmt->fetchAll() ?: [];
    }

    public static function hasException(int $eventId, int $clubId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM event_registration_exceptions WHERE event_id = ? AND club_id = ?'
        );
        $stmt->execute([$eventId, $clubId]);

        return $stmt->fetch() !== false;
    }

    public static function addException(int $eventId, int $clubId): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT IGNORE INTO event_registration_exceptions (event_id, club_id) VALUES (?, ?)'
        );
        $stmt->execute([$eventId, $clubId]);
    }

    public static function removeException(int $eventId, int $clubId): void
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM event_registration_exceptions WHERE event_id = ? AND club_id = ?'
        );
        $stmt->execute([$eventId, $clubId]);
    }

    public static function setExceptions(int $eventId, array $clubIds): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM event_registration_exceptions WHERE event_id = ?')->execute([$eventId]);
            $stmt = $db->prepare('INSERT INTO event_registration_exceptions (event_id, club_id) VALUES (?, ?)');
            foreach ($clubIds as $clubId) {
                $clubId = (int) $clubId;
                if ($clubId > 0) {
                    $stmt->execute([$eventId, $clubId]);
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}