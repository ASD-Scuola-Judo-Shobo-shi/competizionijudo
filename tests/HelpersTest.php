<?php

declare(strict_types=1);

namespace Tests;

use App\Localization;
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        // Set a fixed REQUEST_URI for paginate() tests
        $_SERVER['REQUEST_URI'] = '/test.php';
        $_GET = [];
    }

    public function testEscapeHtml(): void
    {
        self::assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', e('<script>alert(1)</script>'));
        self::assertSame('&amp;', e('&'));
        self::assertSame('', e(''));
    }

    public function testEnvWithDefault(): void
    {
        // env() checks $_ENV, $_SERVER, getenv
        $_ENV['TEST_EXISTS'] = 'hello';
        self::assertSame('hello', env('TEST_EXISTS'));
        self::assertSame('default', env('TEST_MISSING', 'default'));
        unset($_ENV['TEST_EXISTS']);
    }

    public function testConfigWithDotNotation(): void
    {
        // config uses glob on config/*.php so it should work if app.php exists
        $name = config('app.name');
        self::assertNotNull($name);
        self::assertIsString($name);

        // Non-existent key returns default
        self::assertNull(config('nonexistent.key'));
        self::assertSame(42, config('nonexistent.key', 42));
    }

    public function testCsrfTokenGeneratesAndPersists(): void
    {
        // Start fresh
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        $token1 = csrf_token();
        self::assertNotEmpty($token1);
        self::assertSame(64, strlen($token1)); // bin2hex(random_bytes(32)) = 64 chars

        // Same token on second call within same session
        $token2 = csrf_token();
        self::assertSame($token1, $token2);
    }

    public function testCsrfFieldReturnsHiddenInput(): void
    {
        $field = csrf_field();
        self::assertStringStartsWith('<input type="hidden" name="csrf_token" value="', $field);
        self::assertStringEndsWith('">', $field);
    }

    public function testPaginateBasic(): void
    {
        $result = paginate(0, 1, 50);
        self::assertSame(1, $result['page']);
        self::assertSame(0, $result['offset']);
        self::assertSame(1, $result['last_page']);
        self::assertSame(50, $result['per_page']);
        self::assertSame('', $result['links']); // no links for single page
    }

    public function testPaginateMultiPage(): void
    {
        // 150 items, 50 per page = 3 pages, page 2
        $result = paginate(150, 2, 50);
        self::assertSame(2, $result['page']);
        self::assertSame(50, $result['offset']);
        self::assertSame(3, $result['last_page']);
        self::assertSame(150, $result['total']);
        // Should have pagination links HTML
        self::assertStringContainsString('pagination', $result['links']);
        self::assertStringContainsString('page=1', $result['links']);
        self::assertStringContainsString('page=3', $result['links']);
    }

    public function testPaginateClampsPage(): void
    {
        // page=999 clamped to last_page=2
        $result = paginate(100, 999, 50);
        self::assertSame(2, $result['page']);
        self::assertSame(50, $result['offset']);

        // page=0 clamped to 1
        $result = paginate(100, 0, 50);
        self::assertSame(1, $result['page']);
        self::assertSame(0, $result['offset']);
    }

    public function testPaginateUsesAnIndependentPageParameterAndPreservesFilters(): void
    {
        $result = paginate(
            120,
            2,
            50,
            'athletes_page',
            ['event' => '101', 'club_id' => '201'],
            'Enrolled athletes'
        );

        self::assertStringContainsString('event=101', $result['links']);
        self::assertStringContainsString('club_id=201', $result['links']);
        self::assertStringContainsString('athletes_page=3', $result['links']);
        self::assertStringNotContainsString('?page=', $result['links']);
        self::assertStringContainsString('aria-label="Enrolled athletes"', $result['links']);
        self::assertStringContainsString('aria-current="page"', $result['links']);
    }

    public function testPaginateProvidesFirstLastAndFivePageJumps(): void
    {
        Localization::setLocale('en');

        $links = paginate(1_000, 10, 50, 'page', [])['links'];

        self::assertStringContainsString(
            'href="?page=1" aria-label="First page"',
            $links
        );
        self::assertStringContainsString(
            'href="?page=5" aria-label="Go back 5 pages"',
            $links
        );
        self::assertStringContainsString(
            'href="?page=9" aria-label="Previous page"',
            $links
        );
        self::assertStringContainsString(
            'href="?page=11" aria-label="Next page"',
            $links
        );
        self::assertStringContainsString(
            'href="?page=15" aria-label="Go forward 5 pages"',
            $links
        );
        self::assertStringContainsString(
            'href="?page=20" aria-label="Last page"',
            $links
        );

        $firstPageLinks = paginate(1_000, 1, 50, 'page', [])['links'];
        $lastPageLinks = paginate(1_000, 20, 50, 'page', [])['links'];
        self::assertSame(3, substr_count($firstPageLinks, 'pagination-link disabled'));
        self::assertSame(3, substr_count($lastPageLinks, 'pagination-link disabled'));
    }

    public function testTableSortResolutionAcceptsOnlyAllowlistedColumnsAndDirections(): void
    {
        self::assertSame(
            ['column' => 'weight', 'direction' => 'desc'],
            resolve_table_sort('weight', 'desc', ['athlete', 'weight'], 'athlete')
        );
        self::assertSame(
            ['column' => 'athlete', 'direction' => 'asc'],
            resolve_table_sort('DROP TABLE athletes', 'sideways', ['athlete', 'weight'], 'athlete')
        );
        self::assertSame(
            ['column' => 'date', 'direction' => 'desc'],
            resolve_table_sort(null, null, ['date'], 'date', 'desc')
        );
    }

    public function testBasePath(): void
    {
        $path = base_path();
        self::assertDirectoryExists($path);
        self::assertStringEndsWith('competizionijudo', $path);

        $nested = base_path('config/app.php');
        self::assertFileExists($nested);
    }

    public function testAppPathPrefixesConfiguredBasePath(): void
    {
        $_ENV['APP_URL'] = 'https://example.test/subdir';
        $_SERVER['APP_URL'] = 'https://example.test/subdir';

        self::assertSame('/subdir', app_base_path());
        self::assertSame('/subdir/events.php', base_url('/events.php'));
        self::assertSame('/subdir/uploads/events/poster.pdf', base_url('uploads/events/poster.pdf'));
        self::assertSame('https://external.example.test/file.pdf', base_url('https://external.example.test/file.pdf'));

        unset($_ENV['APP_URL'], $_SERVER['APP_URL']);
    }

    public function testTranslateFunction(): void
    {
        // Use a known key
        $result = __('nav.about');
        self::assertIsString($result);
        self::assertNotEmpty($result);
    }
}
