<?php

declare(strict_types=1);

namespace App\Model;

final class AgeClass
{
    /**
     * @param string $name Name of the age class (e.g. "Bambini A")
     * @param int|null $ageBelow The age_below value stored in DB (nullable for Masters)
     * @param int $ageMin Minimum age (inclusive)
     * @param int|null $ageMax Maximum age (inclusive, null for Masters)
     */
    public function __construct(
        public readonly string $name,
        public readonly ?int $ageBelow,
        public readonly int $ageMin,
        public readonly ?int $ageMax,
    ) {
    }

    /**
     * Returns all age classes definition in the given locale.
     * @return self[]
     */
    public static function all(string $locale = 'it'): array
    {
        $definitions = $locale === 'en'
            ? [
                ['name' => 'Children A',  'age_below' => 6,  'age_min' => 4,  'age_max' => 5],
                ['name' => 'Children B',  'age_below' => 8,  'age_min' => 6,  'age_max' => 7],
                ['name' => 'Kids',        'age_below' => 10, 'age_min' => 8,  'age_max' => 9],
                ['name' => 'Youth',       'age_below' => 12, 'age_min' => 10, 'age_max' => 11],
                ['name' => 'Pre-Cadets A', 'age_below' => 13, 'age_min' => 12, 'age_max' => 12],
                ['name' => 'Pre-Cadets B', 'age_below' => 15, 'age_min' => 13, 'age_max' => 14],
                ['name' => 'Cadets',      'age_below' => 18, 'age_min' => 15, 'age_max' => 17],
                ['name' => 'Juniors',     'age_below' => 21, 'age_min' => 18, 'age_max' => 20],
                ['name' => 'Seniors',     'age_below' => 36, 'age_min' => 21, 'age_max' => 35],
                ['name' => 'Masters',     'age_below' => null, 'age_min' => 36, 'age_max' => null],
            ]
            : [
                ['name' => 'Bambini A',   'age_below' => 6,  'age_min' => 4,  'age_max' => 5],
                ['name' => 'Bambini B',   'age_below' => 8,  'age_min' => 6,  'age_max' => 7],
                ['name' => 'Fanciulli',   'age_below' => 10, 'age_min' => 8,  'age_max' => 9],
                ['name' => 'Ragazzi',     'age_below' => 12, 'age_min' => 10, 'age_max' => 11],
                ['name' => 'Esordienti A', 'age_below' => 13, 'age_min' => 12, 'age_max' => 12],
                ['name' => 'Esordienti B', 'age_below' => 15, 'age_min' => 13, 'age_max' => 14],
                ['name' => 'Cadetti',     'age_below' => 18, 'age_min' => 15, 'age_max' => 17],
                ['name' => 'Juniores',     'age_below' => 21, 'age_min' => 18, 'age_max' => 20],
                ['name' => 'Seniores',    'age_below' => 36, 'age_min' => 21, 'age_max' => 35],
                ['name' => 'Master',      'age_below' => null, 'age_min' => 36, 'age_max' => null],
            ];

        return array_map(
            fn(array $d): self => new self($d['name'], $d['age_below'], $d['age_min'], $d['age_max']),
            $definitions
        );
    }

    /**
     * Finds an age class by its age_below value.
     */
    public static function findByAgeBelow(?int $ageBelow, string $locale = 'it'): ?self
    {
        foreach (self::all($locale) as $ac) {
            if ($ac->ageBelow === $ageBelow) {
                return $ac;
            }
        }

        return null;
    }

    /**
     * Calculates the age class for a given birth year and event year.
     *
     * @return array{name: string, age_below: int|null, age_min: int, age_max: int|null, label: string}
     */
    public static function calculate(int $birthYear, int $eventYear = 2026, string $locale = 'it'): array
    {
        $age = $eventYear - $birthYear;

        foreach (self::all($locale) as $ac) {
            if ($age >= $ac->ageMin && ($ac->ageMax === null || $age <= $ac->ageMax)) {
                return [
                    'name' => $ac->name,
                    'age_below' => $ac->ageBelow,
                    'age_min' => $ac->ageMin,
                    'age_max' => $ac->ageMax,
                    'label' => $ac->label($locale),
                ];
            }
        }

        // If the age is below the first category's min age, assign the youngest category
        $all = self::all($locale);
        if ($all !== [] && $age < $all[0]->ageMin) {
            $youngest = $all[0];
            return [
                'name' => $youngest->name,
                'age_below' => $youngest->ageBelow,
                'age_min' => $youngest->ageMin,
                'age_max' => $youngest->ageMax,
                'label' => $youngest->label($locale),
            ];
        }

        $localeLabel = $locale === 'en' ? 'Out of range' : 'Fuori fascia';

        return [
            'name' => $localeLabel,
            'age_below' => null,
            'age_min' => 0,
            'age_max' => null,
            'label' => $localeLabel,
        ];
    }

    /**
     * Returns a localized label for this age class.
     */
    public function label(string $locale = 'it'): string
    {
        $range = $locale === 'en'
            ? $this->formatAgeRangeEn()
            : $this->formatAgeRangeIt();

        return $this->name . ' ' . $range;
    }

    private function formatAgeRangeIt(): string
    {
        if ($this->ageMin === $this->ageMax || $this->ageMax === null) {
            return $this->ageMin >= 36 ? $this->ageMin . '+' : (string) $this->ageMin;
        }

        return $this->ageMin . '-' . $this->ageMax . ' anni';
    }

    private function formatAgeRangeEn(): string
    {
        if ($this->ageMin === $this->ageMax || $this->ageMax === null) {
            return $this->ageMin >= 36 ? $this->ageMin . '+' : (string) $this->ageMin;
        }

        return $this->ageMin . '-' . $this->ageMax . ' yr';
    }

    /**
     * Returns all age class options for a select dropdown.
     * @return array<string, string> age_below => label
     */
    public static function options(string $locale = 'it'): array
    {
        $options = [];
        foreach (self::all($locale) as $ac) {
            $key = $ac->ageBelow !== null ? (string) $ac->ageBelow : '';
            $options[$key] = $ac->label($locale);
        }
        return $options;
    }

    /**
     * Returns age class definitions as JSON for client-side use.
     * Each entry: {name, ageBelow, ageMin, ageMax}
     */
    public static function definitionsJson(string $locale = 'it'): string
    {
        $defs = [];
        foreach (self::all($locale) as $ac) {
            $defs[] = [
                'name' => $ac->name,
                'ageBelow' => $ac->ageBelow,
                'ageMin' => $ac->ageMin,
                'ageMax' => $ac->ageMax,
            ];
        }
        return json_encode($defs, JSON_UNESCAPED_UNICODE);
    }
}
