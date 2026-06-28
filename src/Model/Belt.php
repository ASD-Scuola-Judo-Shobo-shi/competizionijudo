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
    public function label(string $locale = 'it'): string
    {
        // Use the localization system; pass locale context
        $currentLocale = Localization::getLocale();
        Localization::setLocale($locale);
        $translated = Localization::trans("belt.{$this->value}");
        Localization::setLocale($currentLocale);

        return $translated;
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
     * Returns a representative display color for the belt.
     */
    public function color(): string
    {
        return match ($this) {
            self::White => '#f5f5f5',
            self::WhiteYellow => '#fff9c4',
            self::Yellow => '#ffc107',
            self::YellowOrange => '#ffb74d',
            self::Orange => '#fd7e14',
            self::OrangeGreen => '#9ccc65',
            self::Green => '#4caf50',
            self::GreenBlue => '#00bcd4',
            self::Blue => '#2196f3',
            self::Brown => '#795548',
            self::Black => '#424242',
            self::RedWhite => '#ef5350',
            self::Red => '#d32f2f',
        };
    }

    /**
     * Returns a readable text color for the given belt background.
     */
    public function textColor(): string
    {
        return match ($this) {
            self::White, self::WhiteYellow, self::Yellow, self::YellowOrange, self::Orange => '#424242',
            default => '#ffffff',
        };
    }

    /**
     * Returns the color components for the belt. Half-colored belts return two colors.
     * @return array<int, string>
     */
    public function colors(): array
    {
        return match ($this) {
            self::White => ['#f5f5f5'],
            self::WhiteYellow => ['#ffffff', '#ffc107'],
            self::Yellow => ['#ffc107'],
            self::YellowOrange => ['#ffc107', '#fd7e14'],
            self::Orange => ['#fd7e14'],
            self::OrangeGreen => ['#fd7e14', '#4caf50'],
            self::Green => ['#4caf50'],
            self::GreenBlue => ['#4caf50', '#2196f3'],
            self::Blue => ['#2196f3'],
            self::Brown => ['#795548'],
            self::Black => ['#424242'],
            self::RedWhite => ['#ffffff', '#d32f2f'],
            self::Red => ['#d32f2f'],
        };
    }

    /**
     * Returns readable text colors for each color component.
     * @return array<int, string>
     */
    public function textColors(): array
    {
        return match ($this) {
            self::White => ['#424242'],
            self::WhiteYellow => ['#424242', '#424242'],
            self::Yellow => ['#424242'],
            self::YellowOrange => ['#424242', '#424242'],
            self::Orange => ['#424242'],
            self::OrangeGreen => ['#424242', '#ffffff'],
            self::Green => ['#ffffff'],
            self::GreenBlue => ['#ffffff', '#ffffff'],
            self::Blue => ['#ffffff'],
            self::Brown => ['#ffffff'],
            self::Black => ['#ffffff'],
            self::RedWhite => ['#d32f2f', '#ffffff'],
            self::Red => ['#ffffff'],
        };
    }

    /**
     * Checks whether this belt has two distinct color segments.
     */
    public function isHalf(): bool
    {
        return match ($this) {
            self::WhiteYellow,
            self::YellowOrange,
            self::OrangeGreen,
            self::GreenBlue,
            self::RedWhite => true,
            default => false,
        };
    }

    /**
     * Returns UTF-8 colored circle emojis, one per belt color component.
     * @return array<int, string>
     */
    public function circles(): array
    {
        return match ($this) {
            self::White => ["\u{26AA}"],
            self::WhiteYellow => ["\u{26AA}", "\u{1F7E1}"],
            self::Yellow => ["\u{1F7E1}"],
            self::YellowOrange => ["\u{1F7E1}", "\u{1F7E0}"],
            self::Orange => ["\u{1F7E0}"],
            self::OrangeGreen => ["\u{1F7E0}", "\u{1F7E2}"],
            self::Green => ["\u{1F7E2}"],
            self::GreenBlue => ["\u{1F7E2}", "\u{1F535}"],
            self::Blue => ["\u{1F535}"],
            self::Brown => ["\u{1F7E4}"],
            self::Black => ["\u{26AB}"],
            self::RedWhite => ["\u{1F534}", "\u{26AA}"],
            self::Red => ["\u{1F534}"],
        };
    }

    /**
     * Returns a combined circle + label string for display,
     * e.g. "⚪ Bianca" or "🟡 Bianca / 🟠 Gialla".
     */
    public function circleLabel(string $locale = 'it'): string
    {
        $label = $this->label($locale);
        $circles = $this->circles();

        if (!$this->isHalf()) {
            return ($circles[0] ?? '') . ' ' . $label;
        }

        $parts = explode(' / ', $label);
        $result = '';
        foreach ($parts as $i => $part) {
            if ($i > 0) {
                $result .= ' / ';
            }
            $result .= ($circles[$i] ?? '') . ' ' . $part;
        }
        return $result;
    }

    /**
     * Returns the display components for the belt badge.
     * Half-colored belts return two components, others return one.
     * @return list<array{label: string, color: string, textColor: string}>
     */
    public function components(): array
    {
        if ($this->isHalf()) {
            return match ($this) {
                self::WhiteYellow => [
                    ['label' => __('belt.white'), 'color' => '#f5f5f5', 'textColor' => '#424242'],
                    ['label' => __('belt.yellow'), 'color' => '#ffc107', 'textColor' => '#424242'],
                ],
                self::YellowOrange => [
                    ['label' => __('belt.yellow'), 'color' => '#ffc107', 'textColor' => '#424242'],
                    ['label' => __('belt.orange'), 'color' => '#fd7e14', 'textColor' => '#424242'],
                ],
                self::OrangeGreen => [
                    ['label' => __('belt.orange'), 'color' => '#fd7e14', 'textColor' => '#424242'],
                    ['label' => __('belt.green'), 'color' => '#4caf50', 'textColor' => '#ffffff'],
                ],
                self::GreenBlue => [
                    ['label' => __('belt.green'), 'color' => '#4caf50', 'textColor' => '#ffffff'],
                    ['label' => __('belt.blue'), 'color' => '#2196f3', 'textColor' => '#ffffff'],
                ],
                self::RedWhite => [
                    ['label' => __('belt.white'), 'color' => '#f5f5f5', 'textColor' => '#d32f2f'],
                    ['label' => __('belt.red'), 'color' => '#d32f2f', 'textColor' => '#ffffff'],
                ],
            };
        }

        return [
            [
                'label' => __("belt.{$this->value}"),
                'color' => $this->color(),
                'textColor' => $this->textColor(),
            ],
        ];
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
