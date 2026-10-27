<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Model\Belt;

final class EventEntriesViewModel
{
    private const AGE_CLASS_COLORS = [
        'children_a' => '#e1bee7',
        'children_b' => '#ce93d8',
        'kids' => '#ba68c8',
        'youth' => '#ab47bc',
        'pre_cadets_a' => '#9c27b0',
        'pre_cadets_b' => '#8e24aa',
        'cadets' => '#7b1fa2',
        'juniors' => '#6a1b9a',
        'seniors' => '#4a148c',
        'masters' => '#311b92',
    ];

    private const WEIGHT_COLORS = [
        'under-12kg' => '#ffe0b2',
        '12-16kg' => '#ffcdd2',
        '16-20kg' => '#ffb6c1',
        '20-24kg' => '#ffaab9',
        '24-28kg' => '#ff8eb5',
        '28-32kg' => '#ff7eb8',
        '32-36kg' => '#ff6eaa',
        '36-40kg' => '#ff5e99',
        '40-44kg' => '#ff4e88',
        '44-48kg' => '#ff3e78',
        '48-52kg' => '#ff2e68',
        '52-56kg' => '#ff1e58',
        '56-60kg' => '#f0164f',
        '60-64kg' => '#e01046',
        '64-68kg' => '#d00a3d',
        '68-72kg' => '#c00034',
        '72-76kg' => '#b3002d',
        '76-80kg' => '#a30026',
        '80-84kg' => '#93001f',
        '84-88kg' => '#830018',
        '88-92kg' => '#730011',
        '92-96kg' => '#63000a',
        '96-100kg' => '#520004',
        '100+kg' => '#400000',
        'unspecified' => '#b39ddb',
    ];

    /**
     * @param list<array<string, mixed>> $entries
     * @param list<array<string, mixed>> $clubs
     * @param array<int, int> $clubAthleteCounts
     * @param list<array{
     *     key:string,
     *     title:string,
     *     segments:list<array{
     *         label:string,
     *         displayLabel:string,
     *         count:int,
     *         percentage:float,
     *         colors:list<string>,
     *         border:bool
     *     }>,
     *     clubCounts:array<int, array<string, int>>
     * }> $dimensions
     * @param list<array{
     *     category:string,
     *     total:int,
     *     segments:list<array{
     *         label:string,
     *         count:int,
     *         percentage:float,
     *         color:string
     *     }>
     * }> $categoryWeightBars
     * @param list<array{
     *     category:string,
     *     weight:string,
     *     ageMin:int,
     *     athletes:list<array<string, mixed>>
     * }> $athleteGroups
     * @param list<array<string, mixed>> $currentClubEntries
     * @param list<string> $currentClubWeightCategories
     */
    private function __construct(
        public readonly array $entries,
        public readonly array $clubs,
        public readonly array $clubAthleteCounts,
        public readonly array $dimensions,
        public readonly array $categoryWeightBars,
        public readonly array $athleteGroups,
        public readonly array $currentClubEntries,
        public readonly array $currentClubWeightCategories,
        public readonly string $selectedWeightCategory,
        private readonly ?int $currentClubId
    ) {
    }

    public static function empty(): self
    {
        return new self([], [], [], [], [], [], [], [], '', null);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $clubs
     */
    public static function fromRows(
        array $rows,
        array $clubs,
        ?int $currentClubId,
        string $requestedWeightCategory = ''
    ): self {
        $entries = EventEntriesRoster::normalizeRows($rows);
        $categoryCounts = [];
        $categoryMeta = [];
        $weightCounts = [];
        $beltCounts = [];
        $genderCounts = [];
        $clubAthleteCounts = [];
        $clubDimensionCounts = [
            'category' => [],
            'weight' => [],
            'belt' => [],
            'gender' => [],
        ];
        $categoryWeightCounts = [];
        $groups = [];

        foreach ($entries as $entry) {
            $category = (string) $entry['age_category'];
            $weightLabel = (string) $entry['weight_label'];
            $weightGroup = self::groupWeight($weightLabel);
            $belt = (string) $entry['belt'];
            $gender = (string) $entry['gender'];
            $clubId = (int) $entry['club_id'];
            $groupKey = $category . "\0" . $weightLabel;

            $groups[$groupKey] ??= [
                'category' => $category,
                'weight' => $weightLabel,
                'ageMin' => (int) $entry['age_min'],
                'athletes' => [],
            ];
            $groups[$groupKey]['athletes'][] = $entry;

            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
            $categoryWeightCounts[$category][$weightLabel] =
                ($categoryWeightCounts[$category][$weightLabel] ?? 0) + 1;
            $categoryMeta[$category] = [
                'key' => (string) $entry['age_key'],
                'ageMin' => (int) $entry['age_min'],
            ];
            $weightCounts[$weightGroup] = ($weightCounts[$weightGroup] ?? 0) + 1;
            $beltCounts[$belt] = ($beltCounts[$belt] ?? 0) + 1;
            $genderCounts[$gender] = ($genderCounts[$gender] ?? 0) + 1;

            if ($clubId > 0) {
                $clubAthleteCounts[$clubId] = ($clubAthleteCounts[$clubId] ?? 0) + 1;
                self::incrementClubCount($clubDimensionCounts['category'], $clubId, $category);
                self::incrementClubCount($clubDimensionCounts['weight'], $clubId, $weightGroup);
                self::incrementClubCount($clubDimensionCounts['belt'], $clubId, $belt);
                self::incrementClubCount($clubDimensionCounts['gender'], $clubId, $gender);
            }
        }

        $athleteGroups = array_values($groups);
        usort(
            $athleteGroups,
            static fn(array $left, array $right): int =>
                $left['ageMin'] <=> $right['ageMin'] ?: strcmp($left['weight'], $right['weight'])
        );
        $categoryWeightBars = self::categoryWeightBars($categoryWeightCounts, $categoryMeta);

        $dimensions = array_values(array_filter([
            self::dimension(
                'category',
                __('events.entries_category_breakdown'),
                self::categorySegments($categoryCounts, $categoryMeta),
                $clubDimensionCounts['category']
            ),
            self::dimension(
                'weight',
                __('events.entries_weight_breakdown'),
                self::weightSegments($weightCounts),
                $clubDimensionCounts['weight']
            ),
            self::dimension(
                'belt',
                __('events.entries_belt_breakdown'),
                self::beltSegments($beltCounts),
                $clubDimensionCounts['belt']
            ),
            self::dimension(
                'gender',
                __('gender.gender'),
                self::genderSegments($genderCounts),
                $clubDimensionCounts['gender']
            ),
        ], static fn(array $dimension): bool => $dimension['segments'] !== []));

        $currentClubEntries = $currentClubId !== null
            ? array_values(array_filter(
                $entries,
                static fn(array $entry): bool => (int) $entry['club_id'] === $currentClubId
            ))
            : [];
        usort($currentClubEntries, static function (array $left, array $right): int {
            $byWeight = (float) ($left['weight_kg'] ?? 0) <=> (float) ($right['weight_kg'] ?? 0);

            return $byWeight !== 0
                ? $byWeight
                : strcasecmp((string) $left['athlete_name'], (string) $right['athlete_name']);
        });

        $weightCategories = EventEntriesRoster::weightCategories($currentClubEntries);
        $selectedWeightCategory = in_array($requestedWeightCategory, $weightCategories, true)
            ? $requestedWeightCategory
            : '';
        if ($selectedWeightCategory !== '') {
            $currentClubEntries = array_values(array_filter(
                $currentClubEntries,
                static fn(array $entry): bool =>
                    (string) $entry['weight_category'] === $selectedWeightCategory
            ));
        }

        return new self(
            $entries,
            $clubs,
            $clubAthleteCounts,
            $dimensions,
            $categoryWeightBars,
            $athleteGroups,
            $currentClubEntries,
            $weightCategories,
            $selectedWeightCategory,
            $currentClubId
        );
    }

    /**
     * @param array<string, string> $filters
     * @return list<array<string, mixed>>
     */
    public function filteredClubs(array $filters): array
    {
        return array_values(array_filter(
            $this->clubs,
            fn(array $club): bool => $this->clubMatchesFilters($club, $filters)
        ));
    }

    /**
     * @param array<string, string> $filters
     * @return list<array<string, mixed>>
     */
    public function filteredEntries(array $filters): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn(array $entry): bool => self::entryMatchesFilters($entry, $filters)
        ));
    }

    /**
     * @param array<string, string> $filters
     * @return list<array<string, mixed>>
     */
    public function filteredCurrentClubEntries(array $filters): array
    {
        return array_values(array_filter(
            $this->currentClubEntries,
            static fn(array $entry): bool => self::entryMatchesFilters($entry, $filters)
        ));
    }

    /**
     * Build each current-club competition group with its potential opponents.
     *
     * Group filters apply to the age class and weight category headings. Athlete,
     * club, gender, and belt filters apply to every athlete row within each group.
     *
     * @param array<string, string> $filters
     * @return list<array{
     *     ageClass:string,
     *     weightCategory:string,
     *     ageMin:int,
     *     athletes:list<array<string, mixed>>
     * }>
     */
    public function opponentGroups(array $filters = []): array
    {
        if ($this->currentClubId === null) {
            return [];
        }

        $groups = [];
        foreach ($this->entries as $entry) {
            if ((int) ($entry['club_id'] ?? 0) !== $this->currentClubId) {
                continue;
            }
            $key = self::competitionGroupKey($entry);
            $groups[$key] ??= [
                'ageClass' => (string) ($entry['age_category'] ?? ''),
                'weightCategory' => (string) ($entry['weight_label'] ?? ''),
                'ageMin' => (int) ($entry['age_min'] ?? PHP_INT_MAX),
                'athletes' => [],
            ];
            $entry['is_current_club'] = true;
            $groups[$key]['athletes'][] = $entry;
        }

        foreach ($this->entries as $entry) {
            if ((int) ($entry['club_id'] ?? 0) === $this->currentClubId) {
                continue;
            }
            $key = self::competitionGroupKey($entry);
            if (isset($groups[$key])) {
                $entry['is_current_club'] = false;
                $groups[$key]['athletes'][] = $entry;
            }
        }

        $groupFilters = [
            'age_class' => $filters['age_class'] ?? '',
            'weight_category' => $filters['weight_category'] ?? '',
        ];
        $rowFilters = [
            'athlete' => $filters['athlete'] ?? '',
            'club' => $filters['club'] ?? '',
            'gender' => $filters['gender'] ?? '',
            'belt' => $filters['belt'] ?? '',
        ];

        $filteredGroups = [];
        foreach ($groups as $group) {
            $representative = $group['athletes'][0];
            if (!self::entryMatchesFilters($representative, $groupFilters)) {
                continue;
            }

            $athletes = array_values(array_filter(
                $group['athletes'],
                static fn(array $entry): bool => self::entryMatchesFilters($entry, $rowFilters)
            ));
            if ($athletes === []) {
                continue;
            }

            $currentClubAthletes = [];
            $opponents = [];
            foreach ($athletes as $athlete) {
                if ($athlete['is_current_club'] === true) {
                    $currentClubAthletes[] = $athlete;
                    continue;
                }
                $opponents[] = $athlete;
            }
            $group['athletes'] = array_merge(
                EventEntriesRoster::sortedEntries($currentClubAthletes, 'athlete', 'asc'),
                EventEntriesRoster::sortedEntries($opponents, 'club', 'asc')
            );
            $filteredGroups[] = $group;
        }

        usort($filteredGroups, static function (array $left, array $right): int {
            $byAge = $left['ageMin'] <=> $right['ageMin'];
            if ($byAge !== 0) {
                return $byAge;
            }
            return EventEntriesRoster::compareWeightCategories($left['weightCategory'], $right['weightCategory']);
        });

        return $filteredGroups;
    }

    /**
     * Return one page of athletes while retaining the category and weight group headings.
     *
     * @return list<array{
     *     category:string,
     *     weight:string,
     *     ageMin:int,
     *     athletes:list<array<string, mixed>>
     * }>
     */
    public function athleteGroupsPage(int $offset, int $limit): array
    {
        $offset = max(0, $offset);
        $remaining = max(1, $limit);
        $groups = [];

        foreach ($this->athleteGroups as $group) {
            $groupSize = count($group['athletes']);
            if ($offset >= $groupSize) {
                $offset -= $groupSize;
                continue;
            }

            $athletes = array_slice($group['athletes'], $offset, $remaining);
            if ($athletes !== []) {
                $groups[] = [
                    'category' => $group['category'],
                    'weight' => $group['weight'],
                    'ageMin' => $group['ageMin'],
                    'athletes' => $athletes,
                ];
                $remaining -= count($athletes);
            }

            if ($remaining === 0) {
                break;
            }
            $offset = 0;
        }

        return $groups;
    }

    /**
     * @param list<array<string, mixed>>|null $clubs
     * @return list<array<string, mixed>>
     */
    public function sortedClubs(string $sort, string $direction, ?array $clubs = null): array
    {
        $clubs ??= $this->clubs;
        usort($clubs, function (array $left, array $right) use ($sort, $direction): int {
            $leftId = (int) ($left['id'] ?? 0);
            $rightId = (int) ($right['id'] ?? 0);
            $leftValue = match ($sort) {
                'federal_code' => (string) ($left['federal_code'] ?? ''),
                'athletes', 'breakdown' => $this->clubAthleteCounts[$leftId] ?? 0,
                default => (string) ($left['club_name'] ?? ''),
            };
            $rightValue = match ($sort) {
                'federal_code' => (string) ($right['federal_code'] ?? ''),
                'athletes', 'breakdown' => $this->clubAthleteCounts[$rightId] ?? 0,
                default => (string) ($right['club_name'] ?? ''),
            };
            $comparison = is_int($leftValue)
                ? $leftValue <=> $rightValue
                : strnatcasecmp($leftValue, (string) $rightValue);
            if ($comparison === 0) {
                $comparison = strnatcasecmp(
                    (string) ($left['club_name'] ?? ''),
                    (string) ($right['club_name'] ?? '')
                ) ?: ($leftId <=> $rightId);
            }

            return $direction === 'desc' ? -$comparison : $comparison;
        });

        return $clubs;
    }

    /**
     * Sort all athletes before paging, then rebuild the visual category/weight runs.
     *
     * @return list<array{
     *     category:string,
     *     weight:string,
     *     ageMin:int,
     *     athletes:list<array<string, mixed>>
     * }>
     * @param list<array<string, mixed>>|null $entries
     */
    public function athleteGroupsSortedPage(
        string $sort,
        string $direction,
        int $offset,
        int $limit,
        ?array $entries = null
    ): array {
        $entries = EventEntriesRoster::sortedEntries($entries ?? $this->entries, $sort, $direction);
        $entries = array_slice($entries, max(0, $offset), max(1, $limit));
        $groups = [];

        foreach ($entries as $entry) {
            $groupIndex = count($groups) - 1;
            $category = (string) $entry['age_category'];
            $weight = (string) $entry['weight_label'];
            if (
                $groupIndex < 0
                || $groups[$groupIndex]['category'] !== $category
                || $groups[$groupIndex]['weight'] !== $weight
            ) {
                $groups[] = [
                    'category' => $category,
                    'weight' => $weight,
                    'ageMin' => (int) $entry['age_min'],
                    'athletes' => [],
                ];
                $groupIndex++;
            }
            $groups[$groupIndex]['athletes'][] = $entry;
        }

        return $groups;
    }

    /**
     * @param list<array<string, mixed>>|null $entries
     * @return list<array<string, mixed>>
     */
    public function sortedCurrentClubEntries(
        string $sort,
        string $direction,
        ?array $entries = null
    ): array {
        return EventEntriesRoster::sortedEntries($entries ?? $this->currentClubEntries, $sort, $direction);
    }

    /**
     * @param array<string, mixed> $club
     * @param array<string, string> $filters
     */
    private function clubMatchesFilters(array $club, array $filters): bool
    {
        $clubId = (int) ($club['id'] ?? 0);
        foreach ($filters as $field => $filter) {
            $filter = trim($filter);
            if ($filter === '') {
                continue;
            }

            $values = match ($field) {
                'club' => [(string) ($club['club_name'] ?? '')],
                'federal_code' => [(string) ($club['federal_code'] ?? '')],
                'athletes' => [(string) ($this->clubAthleteCounts[$clubId] ?? 0)],
                'breakdown' => $this->clubBreakdownFilterValues($clubId),
                default => null,
            };
            if ($values !== null && !self::filterMatchesAnyValue($filter, $values)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function clubBreakdownFilterValues(int $clubId): array
    {
        $values = [];
        foreach ($this->dimensions as $dimension) {
            $counts = $dimension['clubCounts'][$clubId] ?? [];
            $values[] = (string) $dimension['title'];
            foreach ($dimension['segments'] as $segment) {
                $count = (int) ($counts[$segment['label']] ?? 0);
                if ($count <= 0) {
                    continue;
                }
                $displayLabel = (string) $segment['displayLabel'];
                $values[] = (string) $segment['label'];
                $values[] = $displayLabel;
                $values[] = (string) $count;
                $values[] = $displayLabel . ': ' . $count;
            }
        }

        return $values;
    }

    /** @param array<string, mixed> $entry */
    private static function competitionGroupKey(array $entry): string
    {
        $ageKey = trim((string) ($entry['age_key'] ?? ''));
        if ($ageKey === '') {
            $ageKey = mb_strtolower(trim((string) ($entry['age_category'] ?? '')), 'UTF-8');
        }
        $weightKey = mb_strtolower(trim((string) ($entry['weight_category'] ?? '')), 'UTF-8');
        $weightKey = preg_replace('/\s+/u', '', $weightKey) ?? $weightKey;
        $weightKey = preg_replace('/kg$/u', '', $weightKey) ?? $weightKey;

        return $ageKey . "\0" . $weightKey;
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, string> $filters
     */
    private static function entryMatchesFilters(array $entry, array $filters): bool
    {
        foreach ($filters as $field => $filter) {
            $filter = trim($filter);
            if ($filter === '') {
                continue;
            }

            $values = match ($field) {
                'age_class' => [(string) ($entry['age_category'] ?? '')],
                'weight_category' => [
                    (string) ($entry['weight_category'] ?? ''),
                    (string) ($entry['weight_label'] ?? ''),
                ],
                'athlete' => [
                    (string) ($entry['athlete_name'] ?? ''),
                    (string) ($entry['last_name'] ?? ''),
                    (string) ($entry['first_name'] ?? ''),
                ],
                'club' => [(string) ($entry['club_name'] ?? '')],
                'gender' => [
                    (string) ($entry['gender'] ?? ''),
                    (string) ($entry['gender_label'] ?? ''),
                ],
                'weight' => [(string) ($entry['weight_display'] ?? '')],
                'belt' => [
                    (string) ($entry['belt'] ?? ''),
                    (string) ($entry['belt_label'] ?? ''),
                ],
                default => null,
            };
            if ($values !== null && !self::filterMatchesAnyValue($filter, $values)) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $values */
    private static function filterMatchesAnyValue(string $filter, array $values): bool
    {
        foreach ($values as $value) {
            if (mb_stripos($value, $filter, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, array<string, int>> $counts */
    private static function incrementClubCount(array &$counts, int $clubId, string $key): void
    {
        $counts[$clubId][$key] = ($counts[$clubId][$key] ?? 0) + 1;
    }

    /**
     * @param list<array<string, mixed>> $segments
     * @param array<int, array<string, int>> $clubCounts
     * @return array{key:string, title:string, segments:list<array<string, mixed>>, clubCounts:array<int, array<string, int>>}
     */
    private static function dimension(string $key, string $title, array $segments, array $clubCounts): array
    {
        return compact('key', 'title', 'segments', 'clubCounts');
    }

    /**
     * @param array<string, int> $counts
     * @param array<string, array{key:string, ageMin:int}> $meta
     * @return list<array<string, mixed>>
     */
    private static function categorySegments(array $counts, array $meta): array
    {
        $labels = array_keys($counts);
        usort(
            $labels,
            static fn(string $left, string $right): int =>
                ($meta[$left]['ageMin'] ?? PHP_INT_MAX) <=> ($meta[$right]['ageMin'] ?? PHP_INT_MAX)
        );
        $colors = [];
        foreach ($labels as $label) {
            $colors[$label] = self::AGE_CLASS_COLORS[$meta[$label]['key'] ?? ''] ?? '#b39ddb';
        }

        return self::segments($counts, $colors);
    }

    /**
     * @param array<string, array<string, int>> $counts
     * @param array<string, array{key:string, ageMin:int}> $meta
     * @return list<array<string, mixed>>
     */
    private static function categoryWeightBars(array $counts, array $meta): array
    {
        $categories = array_keys($counts);
        usort(
            $categories,
            static fn(string $left, string $right): int =>
                ($meta[$left]['ageMin'] ?? PHP_INT_MAX) <=> ($meta[$right]['ageMin'] ?? PHP_INT_MAX)
        );
        $largestCategoryTotal = 0;
        foreach ($counts as $weightCounts) {
            $largestCategoryTotal = max($largestCategoryTotal, array_sum($weightCounts));
        }

        $bars = [];
        foreach ($categories as $category) {
            $weightCounts = $counts[$category];
            $weights = array_keys($weightCounts);
            usort($weights, EventEntriesRoster::compareWeightCategories(...));
            $total = array_sum($weightCounts);
            $segments = [];
            foreach ($weights as $weight) {
                $count = $weightCounts[$weight];
                $segments[] = [
                    'label' => $weight,
                    'count' => $count,
                    'percentage' => $largestCategoryTotal > 0
                        ? (float) (($count / $largestCategoryTotal) * 100)
                        : 0.0,
                    'color' => self::WEIGHT_COLORS[self::groupWeight($weight)] ?? '#b39ddb',
                ];
            }
            $bars[] = [
                'category' => $category,
                'total' => $total,
                'segments' => $segments,
            ];
        }

        return $bars;
    }

    /**
     * @param array<string, int> $counts
     * @return list<array<string, mixed>>
     */
    private static function weightSegments(array $counts): array
    {
        return self::segments($counts, self::WEIGHT_COLORS, array_keys(self::WEIGHT_COLORS));
    }

    /**
     * @param array<string, int> $counts
     * @return list<array<string, mixed>>
     */
    private static function beltSegments(array $counts): array
    {
        $order = array_flip(array_map(static fn(Belt $belt): string => $belt->value, Belt::cases()));
        $labels = array_keys($counts);
        usort(
            $labels,
            static fn(string $left, string $right): int =>
                ($order[$left] ?? PHP_INT_MAX) <=> ($order[$right] ?? PHP_INT_MAX)
        );
        $segments = [];
        $total = array_sum($counts);
        foreach ($labels as $label) {
            $belt = Belt::tryFromValue($label);
            $colors = array_column($belt?->components() ?? [], 'color');
            $segments[] = self::segment(
                $label,
                $belt?->label() ?? $label,
                $counts[$label],
                $total,
                $colors !== [] ? $colors : ['#6c757d'],
                true
            );
        }

        return $segments;
    }

    /**
     * @param array<string, int> $counts
     * @return list<array<string, mixed>>
     */
    private static function genderSegments(array $counts): array
    {
        $colors = ['F' => '#f48fb1', 'M' => '#90caf9'];
        $displayLabels = ['F' => __('gender.F'), 'M' => __('gender.M')];

        return self::segments($counts, $colors, ['F', 'M'], $displayLabels);
    }

    /**
     * @param array<string, int> $counts
     * @param array<string, string> $colors
     * @param list<string>|null $order
     * @param array<string, string> $displayLabels
     * @return list<array<string, mixed>>
     */
    private static function segments(
        array $counts,
        array $colors,
        ?array $order = null,
        array $displayLabels = []
    ): array {
        $segments = [];
        $total = array_sum($counts);
        foreach ($order ?? array_keys($counts) as $label) {
            $count = $counts[$label] ?? 0;
            if ($count <= 0) {
                continue;
            }
            $segments[] = self::segment(
                $label,
                $displayLabels[$label] ?? $label,
                $count,
                $total,
                [$colors[$label] ?? '#b39ddb'],
                false
            );
        }

        return $segments;
    }

    /**
     * @param list<string> $colors
     * @return array<string, mixed>
     */
    private static function segment(
        string $label,
        string $displayLabel,
        int $count,
        int $total,
        array $colors,
        bool $border
    ): array {
        return [
            'label' => $label,
            'displayLabel' => $displayLabel,
            'count' => $count,
            'percentage' => $total > 0 ? ($count / $total) * 100 : 0.0,
            'colors' => $colors,
            'border' => $border,
        ];
    }

    private static function groupWeight(string $weight): string
    {
        $weight = ltrim(trim($weight, ' kg'), '-+');
        if (!is_numeric($weight)) {
            return 'unspecified';
        }

        $kilograms = (int) $weight;
        if ($kilograms < 12) {
            return 'under-12kg';
        }

        $lowerBound = 12;
        while ($lowerBound + 4 <= $kilograms) {
            $lowerBound += 4;
        }

        return $lowerBound >= 100
            ? '100+kg'
            : $lowerBound . '-' . ($lowerBound + 4) . 'kg';
    }
}
