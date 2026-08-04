<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Athlete;
use App\Model\Database;

/**
 * Environment-configured account growth limits for clubs (decision D-03).
 * A limit of 0 disables the corresponding quota.
 */
final class ClubQuota
{
    private function __construct()
    {
    }

    public static function athleteLimit(): int
    {
        return max(0, (int) env('CLUB_ATHLETE_LIMIT', 1000));
    }

    public static function entryLimit(): int
    {
        return max(0, (int) env('CLUB_ENTRY_LIMIT', 1000));
    }

    public static function athleteCount(int $clubId): int
    {
        return Athlete::countByClub($clubId);
    }

    public static function entryCountForEvent(int $eventId, int $clubId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM entries WHERE event_id = ? AND club_id = ?'
        );
        $statement->execute([$eventId, $clubId]);

        return (int) $statement->fetchColumn();
    }

    public static function canAddAthletes(int $clubId, int $additional): bool
    {
        $limit = self::athleteLimit();

        return $limit === 0 || self::athleteCount($clubId) + max(0, $additional) <= $limit;
    }

    public static function canRegisterEntry(int $eventId, int $clubId): bool
    {
        $limit = self::entryLimit();

        return $limit === 0 || self::entryCountForEvent($eventId, $clubId) < $limit;
    }

    public static function remainingAthletes(int $clubId): int
    {
        $limit = self::athleteLimit();
        if ($limit === 0) {
            return 0;
        }

        return max(0, $limit - self::athleteCount($clubId));
    }

    public static function remainingEntriesForEvent(int $eventId, int $clubId): int
    {
        $limit = self::entryLimit();
        if ($limit === 0) {
            return 0;
        }

        return max(0, $limit - self::entryCountForEvent($eventId, $clubId));
    }
}
