<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Athlete;
use App\Model\JudoCategory;
use DateTimeImmutable;
use DateTimeZone;

final class TournamentData
{
    public const FIELDS = [
        'last_name', 'first_name', 'gender', 'birth_date', 'weight_kg', 'belt', 'membership_number',
        'type', 'weight_category',
    ];

    public static function cutoff(string $eventDate): string
    {
        return (new DateTimeImmutable($eventDate, new DateTimeZone('Europe/Rome')))
            ->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string|null>
     */
    public static function normalize(array $row): array
    {
        $data = [];
        foreach (self::FIELDS as $field) {
            $value = $row[$field] ?? null;
            $data[$field] = $value === null ? null : (string) $value;
        }
        if (is_numeric($data['weight_kg'])) {
            $data['weight_kg'] = number_format((float) $data['weight_kg'], 2, '.', '');
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string|null>
     */
    public static function categorize(array $row, string $eventDate): array
    {
        $category = JudoCategory::calculate(
            (string) ($row['birth_date'] ?? ''),
            (string) ($row['gender'] ?? ''),
            (float) ($row['weight_kg'] ?? 0),
            Athlete::eventYearFromDate($eventDate)
        );

        return self::normalize(array_merge($row, $category));
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
