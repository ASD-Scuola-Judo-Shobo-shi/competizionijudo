<?php

declare(strict_types=1);

namespace App\Model;

final class JudoCategory
{
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

    public static function normalizeWeight(string $weight): float
    {
        return (float) str_replace(',', '.', trim($weight));
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
        $weight = $weight;

        if ($year === null) {
            return [
                'age_below' => null,
                'program' => '',
                'weight_category' => '',
            ];
        }

        $ageClassResult = AgeClass::calculate($year, $eventYear);
        $name = $ageClassResult['name'];
        $ageBelow = $ageClassResult['age_below'];

        if ($ageBelow === null && $name !== 'Master' && $name !== 'Masters') {
            $program = '';
        } elseif ($ageBelow !== null && $ageBelow <= 12) {
            $program = 'bambini';
        } else {
            $program = 'adulti';
        }

        $categoria = self::weightCategory($name, $gender, $weight);

        return [
            'age_below' => $ageBelow,
            'program' => $program,
            'weight_category' => $categoria,
        ];
    }

    public static function weightCategoryPublic(string $classe, string $gender, float $weight): string
    {
        return self::weightCategory($classe, $gender, $weight);
    }

    private static function weightCategory(string $classe, string $gender, float $weight): string
    {
        // Map localized age class names to internal keys
        $childMap = [
            'Bambini A' => 'Bambini A',
            'Bambini B' => 'Bambini B',
            'Fanciulli' => 'Fanciulli',
            'Ragazzi' => 'Ragazzi',
            'Children A' => 'Bambini A',
            'Children B' => 'Bambini B',
            'Kids' => 'Fanciulli',
            'Youth' => 'Ragazzi',
        ];

        $adultMap = [
            'Esordienti A' => 'Esordienti A',
            'Esordienti B' => 'Esordienti B',
            'Cadetti' => 'Cadetti',
            'Juniores' => 'Junior',
            'Seniores' => 'Senior',
            'Pre-Cadets A' => 'Esordienti A',
            'Pre-Cadets B' => 'Esordienti B',
            'Cadets' => 'Cadetti',
            'Juniors' => 'Junior',
            'Seniors' => 'Senior',
        ];

        $child = [
            'Bambini A' => [16,18,20,22,24,26,28,30,33,36],
            'Bambini B' => [18,20,22,24,26,28,30,33,36,40],
            'Fanciulli' => [20,22,24,26,28,30,33,36,40,45,50],
            'Ragazzi' => [26,28,30,33,36,40,45,50,55,60,66],
        ];

        $adult = [
            'Esordienti A' => ['M' => [36,40,45,50,55,60,66,73], 'F' => [36,40,44,48,52,57,63]],
            'Esordienti B' => ['M' => [38,42,46,50,55,60,66,73,81], 'F' => [40,44,48,52,57,63,70]],
            'Cadetti' => ['M' => [46,50,55,60,66,73,81,90], 'F' => [40,44,48,52,57,63,70]],
            'Junior' => ['M' => [60,66,73,81,90,100], 'F' => [48,52,57,63,70,78]],
            'Senior' => ['M' => [60,66,73,81,90,100], 'F' => [48,52,57,63,70,78]],
        ];

        $childClass = $childMap[$classe] ?? null;
        if ($childClass !== null) {
            foreach ($child[$childClass] as $limit) {
                if ($weight <= $limit) {
                    return '-' . $limit . ' kg';
                }
            }
            return '+' . end($child[$childClass]) . ' kg';
        }

        $adultClass = $adultMap[$classe] ?? (str_starts_with($classe, 'Master') || str_starts_with($classe, 'Masters') ? 'Senior' : null);

        if ($adultClass !== null && isset($adult[$adultClass][$gender])) {
            foreach ($adult[$adultClass][$gender] as $limit) {
                if ($weight <= $limit) {
                    return '-' . $limit . ' kg';
                }
            }
            return '+' . end($adult[$adultClass][$gender]) . ' kg';
        }

        return '';
    }

    /**
     * Returns weight category definitions as JSON for client-side use.
     */
    public static function weightCategoryDefinitionsJson(): string
    {
        $data = [
            'childMap' => [
                'Bambini A' => 'Bambini A',
                'Bambini B' => 'Bambini B',
                'Fanciulli' => 'Fanciulli',
                'Ragazzi' => 'Ragazzi',
                'Children A' => 'Bambini A',
                'Children B' => 'Bambini B',
                'Kids' => 'Fanciulli',
                'Youth' => 'Ragazzi',
            ],
            'adultMap' => [
                'Esordienti A' => 'Esordienti A',
                'Esordienti B' => 'Esordienti B',
                'Cadetti' => 'Cadetti',
                'Juniores' => 'Junior',
                'Seniores' => 'Senior',
                'Pre-Cadets A' => 'Esordienti A',
                'Pre-Cadets B' => 'Esordienti B',
                'Cadets' => 'Cadetti',
                'Juniors' => 'Junior',
                'Seniors' => 'Senior',
            ],
            'child' => [
                'Bambini A' => [16,18,20,22,24,26,28,30,33,36],
                'Bambini B' => [18,20,22,24,26,28,30,33,36,40],
                'Fanciulli' => [20,22,24,26,28,30,33,36,40,45,50],
                'Ragazzi' => [26,28,30,33,36,40,45,50,55,60,66],
            ],
            'adult' => [
                'Esordienti A' => ['M' => [36,40,45,50,55,60,66,73], 'F' => [36,40,44,48,52,57,63]],
                'Esordienti B' => ['M' => [38,42,46,50,55,60,66,73,81], 'F' => [40,44,48,52,57,63,70]],
                'Cadetti' => ['M' => [46,50,55,60,66,73,81,90], 'F' => [40,44,48,52,57,63,70]],
                'Junior' => ['M' => [60,66,73,81,90,100], 'F' => [48,52,57,63,70,78]],
                'Senior' => ['M' => [60,66,73,81,90,100], 'F' => [48,52,57,63,70,78]],
            ],
        ];
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
