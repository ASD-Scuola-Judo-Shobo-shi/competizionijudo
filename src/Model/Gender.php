<?php

declare(strict_types=1);

namespace App\Model;

enum Gender: string
{
    case Male = 'M';
    case Female = 'F';

    /**
     * Returns the localized label for the gender.
     */
    public function label(string $locale = 'it'): string
    {
        return match ($locale) {
            'en' => match ($this) {
                self::Male => 'Male',
                self::Female => 'Female',
            },
            default => match ($this) {
                self::Male => 'Maschio',
                self::Female => 'Femmina',
            },
        };
    }

    /**
     * Tries to parse a value into a Gender. Falls back to null.
     */
    public static function tryFromValue(string $value): ?self
    {
        $normalized = strtoupper(trim($value));

        if ($normalized === 'M' || $normalized === 'MASCHIO' || $normalized === 'MALE') {
            return self::Male;
        }

        if ($normalized === 'F' || $normalized === 'FEMMINA' || $normalized === 'FEMALE') {
            return self::Female;
        }

        return null;
    }

    /**
     * Returns all cases as select options.
     * @return array<string, string> value => label
     */
    public static function options(string $locale = 'it'): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label($locale);
        }
        return $options;
    }
}
