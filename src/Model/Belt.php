<?php

declare(strict_types=1);

namespace App\Model;

use App\Localization;

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
    public function label(?string $locale = null): string
    {
        return Localization::transFor($locale ?? Localization::getLocale(), "belt.{$this->value}");
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
     * Returns a combined circle + label string for display,
     * e.g. "⚪ Bianca" or "🟡 Bianca / 🟠 Gialla".
     */
    public function circleLabel(?string $locale = null): string
    {
        $parts = [];
        foreach ($this->components($locale) as $component) {
            $parts[] = $component['circle'] . ' ' . $component['label'];
        }

        return implode(' / ', $parts);
    }

    /**
     * Returns the display components for the belt badge.
     * Half-colored belts return two components, others return one.
     * @return list<array{label: string, color: string, textColor: string, circle: string}>
     */
    public function components(?string $locale = null): array
    {
        $locale ??= Localization::getLocale();
        $components = [];
        foreach ($this->definition() as $component) {
            $components[] = [
                'label' => Localization::transFor($locale, 'belt.' . $component['key']),
                'color' => $component['color'],
                'textColor' => $component['textColor'],
                'circle' => $component['circle'],
            ];
        }

        return $components;
    }

    /**
     * Returns all cases as select options.
     * @return array<string, string> value => label
     */
    public static function options(?string $locale = null): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label($locale);
        }
        return $options;
    }

    /** @return list<array{key: string, color: string, textColor: string, circle: string}> */
    private function definition(): array
    {
        return match ($this) {
            self::White => [
                ['key' => 'white', 'color' => '#f5f5f5', 'textColor' => '#424242', 'circle' => "\u{26AA}"],
            ],
            self::WhiteYellow => [
                ['key' => 'white', 'color' => '#f5f5f5', 'textColor' => '#424242', 'circle' => "\u{26AA}"],
                ['key' => 'yellow', 'color' => '#ffc107', 'textColor' => '#424242', 'circle' => "\u{1F7E1}"],
            ],
            self::Yellow => [
                ['key' => 'yellow', 'color' => '#ffc107', 'textColor' => '#424242', 'circle' => "\u{1F7E1}"],
            ],
            self::YellowOrange => [
                ['key' => 'yellow', 'color' => '#ffc107', 'textColor' => '#424242', 'circle' => "\u{1F7E1}"],
                ['key' => 'orange', 'color' => '#fd7e14', 'textColor' => '#424242', 'circle' => "\u{1F7E0}"],
            ],
            self::Orange => [
                ['key' => 'orange', 'color' => '#fd7e14', 'textColor' => '#424242', 'circle' => "\u{1F7E0}"],
            ],
            self::OrangeGreen => [
                ['key' => 'orange', 'color' => '#fd7e14', 'textColor' => '#424242', 'circle' => "\u{1F7E0}"],
                ['key' => 'green', 'color' => '#4caf50', 'textColor' => '#ffffff', 'circle' => "\u{1F7E2}"],
            ],
            self::Green => [
                ['key' => 'green', 'color' => '#4caf50', 'textColor' => '#ffffff', 'circle' => "\u{1F7E2}"],
            ],
            self::GreenBlue => [
                ['key' => 'green', 'color' => '#4caf50', 'textColor' => '#ffffff', 'circle' => "\u{1F7E2}"],
                ['key' => 'blue', 'color' => '#2196f3', 'textColor' => '#ffffff', 'circle' => "\u{1F535}"],
            ],
            self::Blue => [
                ['key' => 'blue', 'color' => '#2196f3', 'textColor' => '#ffffff', 'circle' => "\u{1F535}"],
            ],
            self::Brown => [
                ['key' => 'brown', 'color' => '#795548', 'textColor' => '#ffffff', 'circle' => "\u{1F7E4}"],
            ],
            self::Black => [
                ['key' => 'black', 'color' => '#424242', 'textColor' => '#ffffff', 'circle' => "\u{26AB}"],
            ],
            self::RedWhite => [
                ['key' => 'red', 'color' => '#d32f2f', 'textColor' => '#ffffff', 'circle' => "\u{1F534}"],
                ['key' => 'white', 'color' => '#f5f5f5', 'textColor' => '#d32f2f', 'circle' => "\u{26AA}"],
            ],
            self::Red => [
                ['key' => 'red', 'color' => '#d32f2f', 'textColor' => '#ffffff', 'circle' => "\u{1F534}"],
            ],
        };
    }
}
