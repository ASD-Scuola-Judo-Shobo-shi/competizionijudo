<?php

declare(strict_types=1);

namespace App\Model;

final class AgeClass
{
    private const DEFINITIONS = [
        ['key' => 'children_a', 'it' => 'Bambini A', 'en' => 'Children A', 'age_below' => 6, 'age_min' => 4, 'age_max' => 5],
        ['key' => 'children_b', 'it' => 'Bambini B', 'en' => 'Children B', 'age_below' => 8, 'age_min' => 6, 'age_max' => 7],
        ['key' => 'kids', 'it' => 'Fanciulli', 'en' => 'Kids', 'age_below' => 10, 'age_min' => 8, 'age_max' => 9],
        ['key' => 'youth', 'it' => 'Ragazzi', 'en' => 'Youth', 'age_below' => 12, 'age_min' => 10, 'age_max' => 11],
        ['key' => 'pre_cadets_a', 'it' => 'Esordienti A', 'en' => 'Pre-Cadets A', 'age_below' => 13, 'age_min' => 12, 'age_max' => 12],
        ['key' => 'pre_cadets_b', 'it' => 'Esordienti B', 'en' => 'Pre-Cadets B', 'age_below' => 15, 'age_min' => 13, 'age_max' => 14],
        ['key' => 'cadets', 'it' => 'Cadetti', 'en' => 'Cadets', 'age_below' => 18, 'age_min' => 15, 'age_max' => 17],
        ['key' => 'juniors', 'it' => 'Juniores', 'en' => 'Juniors', 'age_below' => 21, 'age_min' => 18, 'age_max' => 20],
        ['key' => 'seniors', 'it' => 'Seniores', 'en' => 'Seniors', 'age_below' => 36, 'age_min' => 21, 'age_max' => 35],
        ['key' => 'masters', 'it' => 'Master', 'en' => 'Masters', 'age_below' => null, 'age_min' => 36, 'age_max' => null],
    ];

    /**
     * @param string $name Name of the age class (e.g. "Bambini A")
     * @param int|null $ageBelow The age_below value stored in DB (nullable for Masters)
     * @param int $ageMin Minimum age (inclusive)
     * @param int|null $ageMax Maximum age (inclusive, null for Masters)
     */
    public function __construct(
        public readonly string $key,
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
        $locale = $locale === 'en' ? 'en' : 'it';

        return array_map(
            fn(array $definition): self => new self(
                $definition['key'],
                $definition[$locale],
                $definition['age_below'],
                $definition['age_min'],
                $definition['age_max']
            ),
            self::DEFINITIONS
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
     * @return array{key: string, name: string, age_below: int|null, age_min: int, age_max: int|null, label: string}
     */
    public static function calculate(int $birthYear, int $eventYear = 0, string $locale = 'it'): array
    {
        if ($eventYear === 0) {
            $eventYear = (int) date('Y');
        }
        $age = $eventYear - $birthYear;

        if ($age < 0) {
            return self::outOfRange($locale);
        }

        foreach (self::all($locale) as $ac) {
            if ($age >= $ac->ageMin && ($ac->ageMax === null || $age <= $ac->ageMax)) {
                return [
                    'key' => $ac->key,
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
                'key' => $youngest->key,
                'name' => $youngest->name,
                'age_below' => $youngest->ageBelow,
                'age_min' => $youngest->ageMin,
                'age_max' => $youngest->ageMax,
                'label' => $youngest->label($locale),
            ];
        }

        return self::outOfRange($locale);
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
     * Returns age class definitions as JSON for client-side use.
     * Each entry: {name, ageBelow, ageMin, ageMax}
     */
    public static function definitionsJson(string $locale = 'it'): string
    {
        $defs = [];
        foreach (self::all($locale) as $ac) {
            $defs[] = [
                'key' => $ac->key,
                'name' => $ac->name,
                'ageBelow' => $ac->ageBelow,
                'ageMin' => $ac->ageMin,
                'ageMax' => $ac->ageMax,
                'label' => $ac->label($locale),
            ];
        }
        return json_encode($defs, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @return array{key: string, name: string, age_below: null, age_min: 0, age_max: null, label: string} */
    private static function outOfRange(string $locale): array
    {
        $label = $locale === 'en' ? 'Out of range' : 'Fuori fascia';

        return [
            'key' => 'out_of_range',
            'name' => $label,
            'age_below' => null,
            'age_min' => 0,
            'age_max' => null,
            'label' => $label,
        ];
    }
}
