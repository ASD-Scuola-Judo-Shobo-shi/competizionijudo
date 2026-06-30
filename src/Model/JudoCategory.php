<?php

declare(strict_types=1);

namespace App\Model;

final class JudoCategory
{
    /** @var array<string, array<string, list<int>>> */
    private const WEIGHT_LIMITS = [
        'children_a' => ['*' => [16,18,20,22,24,26,28,30,33,36]],
        'children_b' => ['*' => [18,20,22,24,26,28,30,33,36,40]],
        'kids' => ['*' => [20,22,24,26,28,30,33,36,40,45,50]],
        'youth' => ['*' => [26,28,30,33,36,40,45,50,55,60,66]],
        'pre_cadets_a' => [
            'M' => [36,40,45,50,55,60,66,73],
            'F' => [36,40,44,48,52,57,63],
        ],
        'pre_cadets_b' => [
            'M' => [38,42,46,50,55,60,66,73,81],
            'F' => [40,44,48,52,57,63,70],
        ],
        'cadets' => [
            'M' => [46,50,55,60,66,73,81,90],
            'F' => [40,44,48,52,57,63,70],
        ],
        'juniors' => [
            'M' => [60,66,73,81,90,100],
            'F' => [48,52,57,63,70,78],
        ],
        'seniors' => [
            'M' => [60,66,73,81,90,100],
            'F' => [48,52,57,63,70,78],
        ],
    ];

    /** @var array<string, string> */
    private const WEIGHT_ALIASES = ['masters' => 'seniors'];

    public static function normalizeGender(string $gender): string
    {
        $s = mb_strtolower(trim($gender), 'UTF-8');

        if ($s === 'm' || str_contains($s, 'masch')) {
            return 'M';
        }

        if ($s === 'f' || str_contains($s, 'femm')) {
            return 'F';
        }

        return strtoupper($gender);
    }

    public static function extractBirthYear(string $value): ?int
    {
        $value = trim($value);

        if (preg_match('/^\d{4}$/', $value)) {
            return (int) $value;
        }

        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $value, $matches)) {
            return (int) $matches[3];
        }

        if (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/', $value, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/(19|20)\d{2}/', $value, $matches)) {
            return (int) $matches[0];
        }

        return null;
    }

    /** @return array{age_below: int|null, program: string, weight_category: string} */
    public static function calculate(string $birth, string $gender, float $weight, int $eventYear = 0): array
    {
        $year = self::extractBirthYear($birth);
        $gender = self::normalizeGender($gender);
        $eventYear = $eventYear > 0 ? $eventYear : (int) date('Y');

        if ($year === null || $year > $eventYear) {
            return self::emptyResult();
        }

        $ageClassResult = AgeClass::calculate($year, $eventYear);
        $classKey = $ageClassResult['key'];
        $ageBelow = $ageClassResult['age_below'];

        if ($classKey === 'out_of_range') {
            return self::emptyResult();
        }

        if ($ageBelow !== null && $ageBelow <= 12) {
            $program = 'bambini';
        } else {
            $program = 'adulti';
        }

        return [
            'age_below' => $ageBelow,
            'program' => $program,
            'weight_category' => self::weightCategory($classKey, $gender, $weight),
        ];
    }

    private static function weightCategory(string $classKey, string $gender, float $weight): string
    {
        if ($weight <= 0) {
            return '';
        }

        $classKey = self::WEIGHT_ALIASES[$classKey] ?? $classKey;
        $definition = self::WEIGHT_LIMITS[$classKey] ?? null;
        if ($definition === null) {
            return '';
        }

        $limits = $definition['*'] ?? $definition[self::normalizeGender($gender)] ?? null;
        if ($limits === null) {
            return '';
        }

        foreach ($limits as $limit) {
            if ($weight <= $limit) {
                return '-' . $limit . ' kg';
            }
        }

        return '+' . $limits[array_key_last($limits)] . ' kg';
    }

    /** @return array{limits: array<string, array<string, list<int>>>, aliases: array<string, string>} */
    public static function weightCategoryDefinitions(): array
    {
        return [
            'limits' => self::WEIGHT_LIMITS,
            'aliases' => self::WEIGHT_ALIASES,
        ];
    }

    public static function weightCategoryDefinitionsJson(): string
    {
        return json_encode(
            self::weightCategoryDefinitions(),
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    /** @return array{age_below: null, program: '', weight_category: ''} */
    private static function emptyResult(): array
    {
        return [
            'age_below' => null,
            'program' => '',
            'weight_category' => '',
        ];
    }
}
