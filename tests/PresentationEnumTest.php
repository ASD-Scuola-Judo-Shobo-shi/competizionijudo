<?php

declare(strict_types=1);

namespace Tests;

use App\Localization;
use App\Model\Belt;
use App\Model\Gender;
use PHPUnit\Framework\TestCase;

final class PresentationEnumTest extends TestCase
{
    private const BELT_LABELS = [
        'it' => [
            'white' => 'Bianca',
            'white_yellow' => 'Bianca / Gialla',
            'yellow' => 'Gialla',
            'yellow_orange' => 'Gialla / Arancio',
            'orange' => 'Arancio',
            'orange_green' => 'Arancio / Verde',
            'green' => 'Verde',
            'green_blue' => 'Verde / Blu',
            'blue' => 'Blu',
            'brown' => 'Marrone',
            'black' => 'Nera',
            'red_white' => 'Rossa / Bianca',
            'red' => 'Rossa',
        ],
        'en' => [
            'white' => 'White',
            'white_yellow' => 'White / Yellow',
            'yellow' => 'Yellow',
            'yellow_orange' => 'Yellow / Orange',
            'orange' => 'Orange',
            'orange_green' => 'Orange / Green',
            'green' => 'Green',
            'green_blue' => 'Green / Blue',
            'blue' => 'Blue',
            'brown' => 'Brown',
            'black' => 'Black',
            'red_white' => 'Red / White',
            'red' => 'Red',
        ],
    ];

    private const GENDER_LABELS = [
        'it' => ['M' => 'Maschio', 'F' => 'Femmina'],
        'en' => ['M' => 'Male', 'F' => 'Female'],
    ];

    protected function setUp(): void
    {
        Localization::setLocale('it');
    }

    public function testEveryBeltHasCompletePresentationInBothLocales(): void
    {
        foreach (self::BELT_LABELS as $locale => $expectedLabels) {
            foreach (Belt::cases() as $belt) {
                self::assertSame($expectedLabels[$belt->value], $belt->label($locale));
                self::assertSame($belt, Belt::tryFromValue($belt->value));

                $components = $belt->components($locale);
                $componentLabels = array_column($components, 'label');
                self::assertSame($expectedLabels[$belt->value], implode(' / ', $componentLabels));
                self::assertCount(str_contains($belt->value, '_') ? 2 : 1, $components);

                foreach ($components as $component) {
                    self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $component['color']);
                    self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $component['textColor']);
                    self::assertNotSame('', $component['circle']);
                }

                self::assertNotSame('', $belt->circleLabel($locale));
            }
        }
    }

    public function testEveryGenderHasCompletePresentationInBothLocales(): void
    {
        foreach (self::GENDER_LABELS as $locale => $expectedLabels) {
            foreach (Gender::cases() as $gender) {
                self::assertSame($expectedLabels[$gender->value], $gender->label($locale));
                self::assertSame($gender, Gender::tryFromValue($gender->value));
                self::assertSame($gender->icon() . ' ' . $expectedLabels[$gender->value], $gender->iconLabel($locale));
            }
        }
    }

    public function testExplicitTranslationsDoNotChangeTheActiveLocale(): void
    {
        Localization::setLocale('it');

        self::assertSame('White', Belt::White->label('en'));
        self::assertSame('Female', Gender::Female->label('en'));
        self::assertSame('it', Localization::getLocale());
        self::assertSame('Bianca', Belt::White->label());
        self::assertSame('Femmina', Gender::Female->label());
    }
}
