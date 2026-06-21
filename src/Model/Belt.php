<?php

declare(strict_types=1);

namespace App\Model;

enum Belt: string
{
    case White = 'white';
    case WhiteYellow = 'white_yellow';
    case Yellow = 'yellow';
    case YellowOrange = 'yellow_orange';
    case Orange = 'orange';
    case OrangeGreen = 'orange_green';
    case Green = 'green';
    case GreenBlue = 'green_blue';
    case Blue = 'blue';
    case Brown = 'brown';
    case Black = 'black';
    case RedWhite = 'red_white';
    case Red = 'red';

    /**
     * Returns the localized label for the belt.
     */
    public function label(string $locale = 'it'): string
    {
        return match ($locale) {
            'en' => match ($this) {
                self::White => 'White',
                self::WhiteYellow => 'White / Yellow',
                self::Yellow => 'Yellow',
                self::YellowOrange => 'Yellow / Orange',
                self::Orange => 'Orange',
                self::OrangeGreen => 'Orange / Green',
                self::Green => 'Green',
                self::GreenBlue => 'Green / Blue',
                self::Blue => 'Blue',
                self::Brown => 'Brown',
                self::Black => 'Black',
                self::RedWhite => 'Red / White',
                self::Red => 'Red',
            },
            default => match ($this) {
                self::White => 'Bianca',
                self::WhiteYellow => 'Bianca / Gialla',
                self::Yellow => 'Gialla',
                self::YellowOrange => 'Gialla / Arancione',
                self::Orange => 'Arancione',
                self::OrangeGreen => 'Arancione / Verde',
                self::Green => 'Verde',
                self::GreenBlue => 'Verde / Blu',
                self::Blue => 'Blu',
                self::Brown => 'Marrone',
                self::Black => 'Nera',
                self::RedWhite => 'Rossa / Bianca',
                self::Red => 'Rossa',
            },
        };
    }

    /**
     * Tries to parse a value into a Belt. Falls back to null.
     */
    public static function tryFromValue(string $value): ?self
    {
        $normalized = str_replace([' ', '-', '_'], '_', strtolower(trim($value)));

        // Handle common variations like "bianca/gialla" -> "bianca_gialla" -> try to map
        $normalized = str_replace('/', '_', $normalized);

        foreach (self::cases() as $case) {
            if ($case->value === $normalized) {
                return $case;
            }
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
