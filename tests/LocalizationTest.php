<?php

declare(strict_types=1);

namespace Tests;

use App\Localization;
use PHPUnit\Framework\TestCase;

final class LocalizationTest extends TestCase
{
    protected function setUp(): void
    {
        Localization::setLocale('it');
    }

    public function testSetAndGetLocale(): void
    {
        self::assertSame('it', Localization::getLocale());
        Localization::setLocale('en');
        self::assertSame('en', Localization::getLocale());
        Localization::setLocale('it');
    }

    public function testTransWithSimpleKey(): void
    {
        $result = Localization::trans('nav.home');
        self::assertIsString($result);
        self::assertNotEmpty($result);
    }

    public function testTransWithUnknownKeyReturnsKey(): void
    {
        $result = Localization::trans('nonexistent.key.path');
        self::assertSame('nonexistent.key.path', $result);
    }

    public function testTransWithReplacements(): void
    {
        $result = Localization::trans('club.register.errors.registration_failed', ['message' => 'DB error']);
        self::assertStringContainsString('DB error', $result);
    }

    public function testTransReturnsEnglishWhenLocaleSet(): void
    {
        Localization::setLocale('en');
        $result = Localization::trans('nav.home');
        self::assertSame('Home', $result);
        Localization::setLocale('it');
    }

    public function testTransWithNestedKey(): void
    {
        $result = Localization::trans('admin.login.title');
        self::assertIsString($result);
        self::assertNotEmpty($result);
    }
}
