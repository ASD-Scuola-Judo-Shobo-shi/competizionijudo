<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Localization;
use App\Model\AgeClass;
use App\Model\Belt;
use App\Model\Gender;
use App\Model\JudoCategory;

final class EventEntriesRoster
{
    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function normalizeRows(array $rows): array
    {
        return array_map(self::normalizeEntry(...), $rows);
    }

    /**
     * @param list<array<string, mixed>> $entries Normalized event entries.
     * @return list<array<string, mixed>>
     */
    public static function sortedEntries(array $entries, string $sort, string $direction): array
    {
        usort($entries, static function (array $left, array $right) use ($sort, $direction): int {
            $leftValue = self::entrySortValue($left, $sort);
            $rightValue = self::entrySortValue($right, $sort);
            $comparison = self::compareSortValues($leftValue, $rightValue);
            if ($comparison === 0 && $sort === 'age_class') {
                $comparison = self::weightCategoryValue((string) ($left['weight_category'] ?? ''))
                    <=> self::weightCategoryValue((string) ($right['weight_category'] ?? ''));
            }
            if ($comparison === 0) {
                $comparison = strnatcasecmp(
                    (string) ($left['athlete_name'] ?? ''),
                    (string) ($right['athlete_name'] ?? '')
                ) ?: ((int) ($left['entry_id'] ?? 0) <=> (int) ($right['entry_id'] ?? 0));
            }

            return $direction === 'desc' ? -$comparison : $comparison;
        });

        return $entries;
    }

    /**
     * @param list<array<string, mixed>> $entries Normalized event entries.
     * @return list<array{club_name: string, federal_code: string, athletes: list<array<string, mixed>>}>
     */
    public static function clubRosters(array $entries): array
    {
        $rosters = [];
        foreach (self::sortedEntries($entries, 'club', 'asc') as $entry) {
            $clubId = (int) $entry['club_id'];
            $rosters[$clubId] ??= [
                'club_name' => (string) $entry['club_name'],
                'federal_code' => (string) $entry['federal_code'],
                'athletes' => [],
            ];
            $rosters[$clubId]['athletes'][] = $entry;
        }

        return array_values($rosters);
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
        usort($categories, self::compareWeightCategories(...));

        return $categories;
    }

    public static function compareWeightCategories(string $left, string $right): int
    {
        $byWeight = self::weightCategoryValue($left) <=> self::weightCategoryValue($right);

        return $byWeight !== 0 ? $byWeight : strcasecmp($left, $right);
    }

    private static function weightCategoryValue(string $category): float
    {
        preg_match('/\d+(?:[.,]\d+)?/', $category, $match);

        return isset($match[0]) ? (float) str_replace(',', '.', $match[0]) : INF;
    }

    /** @param array<string, mixed> $entry */
    private static function entrySortValue(array $entry, string $sort): string|int|float
    {
        return match ($sort) {
            'age_class' => (int) ($entry['age_min'] ?? PHP_INT_MAX),
            'weight' => (float) ($entry['weight_kg'] ?? 0.0),
            'weight_category' => self::weightCategoryValue(
                (string) ($entry['weight_category'] ?? '')
            ),
            'club' => (string) ($entry['club_name'] ?? ''),
            'gender' => (string) ($entry['gender'] ?? ''),
            'belt' => self::beltSortValue((string) ($entry['belt'] ?? '')),
            default => (string) ($entry['athlete_name'] ?? ''),
        };
    }

    private static function compareSortValues(string|int|float $left, string|int|float $right): int
    {
        if (is_int($left) || is_float($left)) {
            return $left <=> $right;
        }

        return strnatcasecmp($left, (string) $right);
    }

    private static function beltSortValue(string $belt): int
    {
        foreach (Belt::cases() as $position => $candidate) {
            if ($candidate->value === $belt) {
                return $position;
            }
        }

        return PHP_INT_MAX;
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
            ? AgeClass::calculate($birthYear, $eventYear, Localization::getLocale())
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

    private static function formatWeight(float $weight): string
    {
        return rtrim(rtrim(number_format($weight, 2, '.', ''), '0'), '.');
    }
}
