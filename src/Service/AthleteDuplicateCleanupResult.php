<?php

declare(strict_types=1);

namespace App\Service;

final class AthleteDuplicateCleanupResult
{
    /**
     * @param list<array{
     *     club_id: int,
     *     survivor_id: int,
     *     duplicate_ids: list<int>,
     *     last_name: string,
     *     first_name: string,
     *     birth_date: string,
     *     entry_moves: int,
     *     resolutions: array<int, array<string, string>>
     * }> $groups
     * @param list<array{
     *     club_id: int,
     *     athlete_ids: list<int>,
     *     last_name: string,
     *     first_name: string,
     *     birth_date: string,
     *     reason: string,
     *     overlapping_event_ids: list<int>
     * }> $blockedGroups
     * @param list<array{
     *     club_id: int,
     *     athlete_ids: list<int>,
     *     last_name: string,
     *     first_name: string,
     *     birth_dates: list<string>
     * }> $nameCollisions
     */
    public function __construct(
        public readonly bool $applied,
        public readonly array $groups,
        public readonly array $blockedGroups,
        public readonly array $nameCollisions
    ) {
    }

    public function duplicateAthletes(): int
    {
        return array_sum(array_map(
            static fn(array $group): int => count($group['duplicate_ids']),
            $this->groups
        ));
    }

    public function entryMoves(): int
    {
        return array_sum(array_column($this->groups, 'entry_moves'));
    }
}
