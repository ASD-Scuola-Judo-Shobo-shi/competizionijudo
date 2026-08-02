<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Model\AgeClass;
use App\Model\Belt;
use App\Model\Gender;
use App\Model\JudoCategory;

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
        public readonly string $selectedWeightCategory
    ) {
    }

    public static function empty(): self
    {
        return new self([], [], [], [], [], [], [], [], '');
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
        $entries = array_map(self::normalizeEntry(...), $rows);
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

        $weightCategories = self::weightCategories($currentClubEntries);
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
            $selectedWeightCategory
        );
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<string>
     */
    public static function weightCategories(array $entries): array
    {
        $categories = [];
        foreach ($entries as $entry) {
            $category = trim((string) ($entry['weight_category'] ?? ''));
            if ($category !== '') {
                $categories[$category] = true;
            }
        }

        $categories = array_keys($categories);
        usort($categories, static function (string $left, string $right): int {
            $byWeight = self::weightCategoryValue($left) <=> self::weightCategoryValue($right);

            return $byWeight !== 0 ? $byWeight : strcasecmp($left, $right);
        });

        return $categories;
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
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normalizeEntry(array $row): array
    {
        $birthDate = (string) ($row['birth_date'] ?? '');
        $eventDate = (string) ($row['event_date'] ?? '');
        $birthYear = JudoCategory::extractBirthYear($birthDate);
        $eventYear = $eventDate !== '' ? (int) substr($eventDate, 0, 4) : (int) date('Y');
        $ageClass = $birthYear !== null
            ? AgeClass::calculate($birthYear, $eventYear)
            : ['key' => '', 'age_min' => PHP_INT_MAX, 'label' => ''];
        $genderValue = (string) ($row['gender'] ?? '');
        $gender = Gender::tryFromValue($genderValue);
        $beltValue = (string) ($row['belt'] ?? '');
        $weight = is_numeric($row['weight_kg'] ?? null) ? (float) $row['weight_kg'] : null;
        $weightCategory = trim((string) ($row['weight_category'] ?? ''));
        $lastName = (string) ($row['last_name'] ?? '');
        $firstName = (string) ($row['first_name'] ?? '');

        return [
            ...$row,
            'club_id' => (int) ($row['club_id'] ?? 0),
            'club_name' => (string) ($row['club_name'] ?? ''),
            'federal_code' => (string) ($row['federal_code'] ?? ''),
            'last_name' => $lastName,
            'first_name' => $firstName,
            'athlete_name' => trim($lastName . ' ' . $firstName),
            'gender' => $genderValue,
            'gender_icon' => $gender?->icon() ?? $genderValue,
            'gender_label' => $gender?->iconLabel() ?? $genderValue,
            'weight_kg' => $weight,
            'weight_display' => $weight !== null ? self::formatWeight($weight) . ' kg' : '—',
            'weight_category' => $weightCategory,
            'weight_label' => $weightCategory !== '' ? $weightCategory : __('events.no_weight'),
            'belt' => $beltValue,
            'belt_label' => Belt::tryFromValue($beltValue)?->label() ?? $beltValue,
            'birth_date' => $birthDate,
            'event_date' => $eventDate,
            'age_key' => (string) $ageClass['key'],
            'age_min' => (int) $ageClass['age_min'],
            'age_category' => (string) $ageClass['label'] !== ''
                ? (string) $ageClass['label']
                : __('events.no_category'),
        ];
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
            usort($weights, static function (string $left, string $right): int {
                $byWeight = self::weightCategoryValue($left) <=> self::weightCategoryValue($right);

                return $byWeight !== 0 ? $byWeight : strcasecmp($left, $right);
            });
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

    private static function weightCategoryValue(string $category): float
    {
        preg_match('/\d+(?:[.,]\d+)?/', $category, $match);

        return isset($match[0]) ? (float) str_replace(',', '.', $match[0]) : INF;
    }

    private static function formatWeight(float $weight): string
    {
        return rtrim(rtrim(number_format($weight, 2, '.', ''), '0'), '.');
    }
}
