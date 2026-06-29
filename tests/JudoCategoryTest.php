<?php

declare(strict_types=1);

namespace Tests;

use App\Model\AgeClass;
use App\Model\JudoCategory;
use PHPUnit\Framework\TestCase;

final class JudoCategoryTest extends TestCase
{
    public function testNormalizeGenderM(): void
    {
        self::assertSame('M', JudoCategory::normalizeGender('M'));
        self::assertSame('M', JudoCategory::normalizeGender('m'));
        self::assertSame('M', JudoCategory::normalizeGender('maschile'));
        self::assertSame('M', JudoCategory::normalizeGender('maschio'));
    }

    public function testNormalizeGenderF(): void
    {
        self::assertSame('F', JudoCategory::normalizeGender('F'));
        self::assertSame('F', JudoCategory::normalizeGender('f'));
        self::assertSame('F', JudoCategory::normalizeGender('femmina'));
        self::assertSame('F', JudoCategory::normalizeGender('femminile'));
    }

    public function testNormalizeGenderUnknown(): void
    {
        self::assertSame('X', JudoCategory::normalizeGender('X'));
    }

    public function testNormalizeWeight(): void
    {
        self::assertSame(35.5, JudoCategory::normalizeWeight('35,5'));
        self::assertSame(35.5, JudoCategory::normalizeWeight('35.5'));
        self::assertSame(35.0, JudoCategory::normalizeWeight('  35  '));
    }

    public function testExtractBirthYear(): void
    {
        // Plain year
        self::assertSame(2016, JudoCategory::extractBirthYear('2016'));
        // dd/mm/yyyy
        self::assertSame(2016, JudoCategory::extractBirthYear('15/01/2016'));
        self::assertSame(2016, JudoCategory::extractBirthYear('15-01-2016'));
        // yyyy-mm-dd
        self::assertSame(2016, JudoCategory::extractBirthYear('2016-01-15'));
        self::assertSame(2016, JudoCategory::extractBirthYear('2016/01/15'));
        // Embedded year
        self::assertSame(2016, JudoCategory::extractBirthYear('some 2016 text'));
        // Invalid
        self::assertNull(JudoCategory::extractBirthYear(''));
        self::assertNull(JudoCategory::extractBirthYear('not-a-date'));
    }

    public function testEveryAgeClassBoundaryMatchesGeneratedClientDefinitions(): void
    {
        foreach (['it', 'en'] as $locale) {
            $classes = AgeClass::all($locale);
            $clientDefinitions = json_decode(
                AgeClass::definitionsJson($locale),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            self::assertCount(count($classes), $clientDefinitions);
            foreach ($classes as $index => $class) {
                self::assertSame($class->key, $clientDefinitions[$index]['key']);
                self::assertSame($class->name, $clientDefinitions[$index]['name']);
                self::assertSame($class->ageBelow, $clientDefinitions[$index]['ageBelow']);
                self::assertSame($class->ageMin, $clientDefinitions[$index]['ageMin']);
                self::assertSame($class->ageMax, $clientDefinitions[$index]['ageMax']);
                self::assertSame($class->label($locale), $clientDefinitions[$index]['label']);

                $minimum = AgeClass::calculate(2026 - $class->ageMin, 2026, $locale);
                self::assertSame($class->key, $minimum['key']);
                if ($class->ageMax !== null) {
                    $maximum = AgeClass::calculate(2026 - $class->ageMax, 2026, $locale);
                    self::assertSame($class->key, $maximum['key']);
                }
            }
        }
    }

    public function testEveryGenderAndWeightThresholdUsesExportedDefinition(): void
    {
        $definitions = JudoCategory::weightCategoryDefinitions();
        $clientDefinitions = json_decode(
            JudoCategory::weightCategoryDefinitionsJson(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertSame($definitions, $clientDefinitions);

        $classes = [];
        foreach (AgeClass::all() as $class) {
            $classes[$class->key] = $class;
        }

        foreach ($definitions['limits'] as $classKey => $genderLimits) {
            $class = $classes[$classKey];
            $birth = (string) (2026 - $class->ageMin);
            foreach ($genderLimits as $genderKey => $limits) {
                $genders = $genderKey === '*' ? ['M', 'F'] : [$genderKey];
                foreach ($genders as $gender) {
                    foreach ($limits as $index => $limit) {
                        $atThreshold = JudoCategory::calculate($birth, $gender, (float) $limit, 2026);
                        self::assertSame('-' . $limit . ' kg', $atThreshold['weight_category']);

                        $nextLimit = $limits[$index + 1] ?? null;
                        $aboveThreshold = JudoCategory::calculate($birth, $gender, $limit + 0.01, 2026);
                        self::assertSame(
                            $nextLimit === null ? '+' . $limit . ' kg' : '-' . $nextLimit . ' kg',
                            $aboveThreshold['weight_category']
                        );
                    }
                }
            }
        }
    }

    public function testMasterUsesSeniorLimitsForBothGenders(): void
    {
        $male = JudoCategory::calculate('1980-01-01', 'M', 100.01, 2026);
        $female = JudoCategory::calculate('1980-01-01', 'F', 78.01, 2026);

        self::assertNull($male['age_below']);
        self::assertSame('adulti', $male['program']);
        self::assertSame('+100 kg', $male['weight_category']);
        self::assertNull($female['age_below']);
        self::assertSame('adulti', $female['program']);
        self::assertSame('+78 kg', $female['weight_category']);
    }

    public function testEventYearChangesAgeAndWeightClassAtBoundary(): void
    {
        $atTwelve = JudoCategory::calculate('2014-06-15', 'M', 38.0, 2026);
        $atThirteen = JudoCategory::calculate('2014-06-15', 'M', 38.0, 2027);

        self::assertSame(13, $atTwelve['age_below']);
        self::assertSame('-40 kg', $atTwelve['weight_category']);
        self::assertSame(15, $atThirteen['age_below']);
        self::assertSame('-38 kg', $atThirteen['weight_category']);
        self::assertSame('adulti', $atTwelve['program']);
        self::assertSame('adulti', $atThirteen['program']);
    }

    public function testInvalidAndFutureBirthValuesReturnNoCategory(): void
    {
        foreach (['', 'not-a-date', '2027-01-01'] as $birth) {
            $result = JudoCategory::calculate($birth, 'M', 70.0, 2026);
            self::assertSame('', $result['program']);
            self::assertSame('', $result['weight_category']);
            self::assertNull($result['age_below']);
        }

        self::assertSame('out_of_range', AgeClass::calculate(2027, 2026)['key']);
        self::assertSame('children_a', AgeClass::calculate(2023, 2026)['key']);
    }

    public function testInvalidGenderAndWeightDoNotProduceWeightCategory(): void
    {
        self::assertSame('', JudoCategory::calculate('2000', 'X', 70.0, 2026)['weight_category']);
        self::assertSame('', JudoCategory::calculate('2000', 'M', 0.0, 2026)['weight_category']);
    }

    public function testLocalizedClassNamesResolveThroughStableKeys(): void
    {
        foreach (['it', 'en'] as $locale) {
            foreach (AgeClass::all($locale) as $class) {
                self::assertNotSame(
                    '',
                    JudoCategory::weightCategoryPublic($class->name, 'M', 1.0),
                    $class->key
                );
            }
        }
    }
}
