<?php

declare(strict_types=1);

namespace Tests;

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

    public function testCalculateBambini(): void
    {
        // 8 year old male, 25kg
        $result = JudoCategory::calculate('2018-06-15', 'M', 25.0, 2026);
        self::assertIsArray($result);
        self::assertSame('bambini', $result['program']);
        self::assertStringContainsString('kg', $result['weight_category']);
    }

    public function testCalculateAdult(): void
    {
        // 20 year old male, 70kg
        $result = JudoCategory::calculate('2006-06-15', 'M', 70.0, 2026);
        self::assertIsArray($result);
        self::assertSame('adulti', $result['program']);
        self::assertStringContainsString('kg', $result['weight_category']);
    }

    public function testCalculateInvalidBirthReturnsEmpty(): void
    {
        $result = JudoCategory::calculate('', 'M', 70.0, 2026);
        self::assertSame('', $result['program']);
        self::assertSame('', $result['weight_category']);
        self::assertNull($result['age_below']);
    }

    public function testWeightCategoryDefinitionsJson(): void
    {
        $json = JudoCategory::weightCategoryDefinitionsJson();
        self::assertJson($json);
        $data = json_decode($json, true);
        self::assertArrayHasKey('childMap', $data);
        self::assertArrayHasKey('adultMap', $data);
        self::assertArrayHasKey('child', $data);
        self::assertArrayHasKey('adult', $data);
        self::assertArrayHasKey('M', $data['adult']['Senior']);
        self::assertArrayHasKey('F', $data['adult']['Senior']);
    }
}
