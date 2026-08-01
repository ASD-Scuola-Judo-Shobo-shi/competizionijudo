<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Belt;

final class AthleteFieldReconciler
{
    /**
     * @param array{
     *     last_name: string,
     *     first_name: string,
     *     gender: string,
     *     birth_date: string,
     *     weight_kg: float|null,
     *     belt: string,
     *     membership_number: string|null,
     *     notes: string|null
     * } $database
     * @param array<string, string> $imported
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    public function reconcile(array $database, array $imported): array
    {
        $resolutions = [];
        $row = [
            'last_name' => $this->titleCaseName($database['last_name']),
            'first_name' => $this->titleCaseName($database['first_name']),
            'birth_date' => $database['birth_date'],
        ];
        if ($database['last_name'] !== ($imported['last_name'] ?? '')) {
            $resolutions['last_name'] = 'normalized';
        }
        if ($database['first_name'] !== ($imported['first_name'] ?? '')) {
            $resolutions['first_name'] = 'normalized';
        }

        [$row['gender'], $resolution] = $this->databaseValue(
            $database['gender'],
            $imported['gender'] ?? ''
        );
        if ($resolution !== null) {
            $resolutions['gender'] = $resolution;
        }

        [$row['weight_kg'], $resolution] = $this->weight(
            $database['weight_kg'],
            $imported['weight_kg'] ?? ''
        );
        if ($resolution !== null) {
            $resolutions['weight_kg'] = $resolution;
        }

        [$row['belt'], $resolution] = $this->belt(
            $database['belt'],
            $imported['belt'] ?? ''
        );
        if ($resolution !== null) {
            $resolutions['belt'] = $resolution;
        }

        [$row['membership_number'], $resolution] = $this->text(
            $database['membership_number'],
            $imported['membership_number'] ?? '',
            ' / ',
            80,
            true
        );
        if ($resolution !== null) {
            $resolutions['membership_number'] = $resolution;
        }

        [$row['notes'], $resolution] = $this->text(
            $database['notes'],
            $imported['notes'] ?? '',
            "\n",
            65_535,
            false
        );
        if ($resolution !== null) {
            $resolutions['notes'] = $resolution;
        }

        return [$row, $resolutions];
    }

    public function titleCaseName(string $value): string
    {
        return mb_convert_case($this->cleanText($value), MB_CASE_TITLE, 'UTF-8');
    }

    public function formatWeight(?float $weight): string
    {
        if ($weight === null) {
            return '';
        }

        return rtrim(rtrim(number_format($weight, 2, '.', ''), '0'), '.');
    }

    /** @return array{0: string, 1: string|null} */
    private function databaseValue(string $database, string $imported): array
    {
        if ($database === '') {
            return [$imported, $imported !== '' ? 'used_imported' : null];
        }
        if ($imported === '') {
            return [$database, 'used_database'];
        }
        if ($database === $imported) {
            return [$database, null];
        }

        return [$database, 'kept_database'];
    }

    /** @return array{0: string, 1: string|null} */
    private function weight(?float $database, string $imported): array
    {
        if ($database === null) {
            return [$imported, $imported !== '' ? 'used_imported' : null];
        }

        $databaseWeight = $this->formatWeight($database);
        if ($imported === '') {
            return [$databaseWeight, 'used_database'];
        }
        if (is_numeric($imported) && abs($database - (float) $imported) < 0.0001) {
            return [$databaseWeight, null];
        }

        return [$databaseWeight, 'kept_database'];
    }

    /** @return array{0: string, 1: string|null} */
    private function belt(string $database, string $imported): array
    {
        if ($database === '') {
            return [$imported, $imported !== '' ? 'used_imported' : null];
        }
        if ($imported === '') {
            return [$database, 'used_database'];
        }
        if ($database === $imported) {
            return [$database, null];
        }

        $databaseRank = $this->beltRank($database);
        $importedRank = $this->beltRank($imported);
        if ($databaseRank === null || $importedRank === null) {
            return [$database, 'kept_database'];
        }

        return [
            $importedRank > $databaseRank ? $imported : $database,
            'higher_belt',
        ];
    }

    /** @return array{0: string, 1: string|null} */
    private function text(
        ?string $database,
        string $imported,
        string $separator,
        int $maximumLength,
        bool $caseInsensitive
    ): array {
        $database = $database !== null && trim($database) !== '' ? trim($database) : null;
        $imported = trim($imported);
        $imported = $imported !== '' ? $imported : null;

        if ($database === null) {
            return [$imported ?? '', $imported !== null ? 'used_imported' : null];
        }
        if ($imported === null) {
            return [$database, 'used_database'];
        }
        if ($this->containsTextValue($database, $imported, $separator, $caseInsensitive)) {
            return [$database, null];
        }

        $combined = $this->containsTextValue($imported, $database, $separator, $caseInsensitive)
            ? $imported
            : $database . $separator . $imported;
        if (mb_strlen($combined, 'UTF-8') > $maximumLength) {
            return [$database, 'kept_database'];
        }

        return [$combined, 'combined'];
    }

    private function containsTextValue(
        string $container,
        string $value,
        string $separator,
        bool $caseInsensitive
    ): bool {
        $container = $separator . $container . $separator;
        $value = $separator . $value . $separator;
        if ($caseInsensitive) {
            $container = mb_strtolower($container, 'UTF-8');
            $value = mb_strtolower($value, 'UTF-8');
        }

        return str_contains($container, $value);
    }

    private function beltRank(string $value): ?int
    {
        $belt = Belt::tryFromValue($value);
        if ($belt === null) {
            return null;
        }

        $rank = array_search($belt, Belt::cases(), true);

        return is_int($rank) ? $rank : null;
    }

    private function cleanText(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
