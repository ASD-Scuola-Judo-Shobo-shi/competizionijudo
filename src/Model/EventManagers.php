<?php

declare(strict_types=1);

namespace App\Model;

use PDO;

final class EventManagers
{
    /** SQL for queries against the events table, with two club ID parameters. */
    public static function clubScopeSql(): string
    {
        return '(owner_club_id = ? OR EXISTS (
            SELECT 1 FROM event_managers em WHERE em.event_id = events.id AND em.club_id = ?
        ))';
    }

    /** @return list<int> */
    public static function clubIdsForEvent(int $eventId): array
    {
        $statement = Database::connection()->prepare('SELECT club_id FROM event_managers WHERE event_id = ? ORDER BY club_id');
        $statement->execute([$eventId]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param list<int> $clubIds */
    public static function synchronize(PDO $database, int $eventId, array $clubIds): void
    {
        $database->prepare('DELETE FROM event_managers WHERE event_id = ?')->execute([$eventId]);
        $insert = $database->prepare('INSERT INTO event_managers (event_id, club_id) VALUES (?, ?)');
        foreach (array_unique($clubIds) as $clubId) {
            $insert->execute([$eventId, $clubId]);
        }
    }
}
