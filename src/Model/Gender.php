<?php

declare(strict_types=1);

namespace App\Model;

use App\Localization;

enum Gender: string
{
    case Male = 'M';
    case Female = 'F';

    /**
     * Returns the localized label for the gender.
     */
    public function label(string $locale = 'it'): string
    {
        $currentLocale = Localization::getLocale();
        Localization::setLocale($locale);
        $translated = Localization::trans("gender.{$this->value}");
        Localization::setLocale($currentLocale);

        return $translated;
    }

    /**
     * Returns a UTF-8 icon for the gender.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Male => "\u{2642}",
            self::Female => "\u{2640}",
        };
    }

    /**
     * Returns icon + label combined, e.g. "♂ Maschio" or "♀ Female".
     */
    public function iconLabel(string $locale = 'it'): string
    {
        return $this->icon() . ' ' . $this->label($locale);
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
