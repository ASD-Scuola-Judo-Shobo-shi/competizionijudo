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
    public function label(?string $locale = null): string
    {
        return Localization::transFor($locale ?? Localization::getLocale(), "gender.{$this->value}");
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
    public function iconLabel(?string $locale = null): string
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
}
