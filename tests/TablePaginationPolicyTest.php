<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class TablePaginationPolicyTest extends TestCase
{
    public function testEveryRecordTableDeclaresServerOrClientPagination(): void
    {
        $views = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__) . '/views')
        );

        foreach ($views as $view) {
            if (!$view instanceof SplFileInfo || !$view->isFile() || $view->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($view->getPathname());
            self::assertIsString($source);
            preg_match_all('/<table\b(?<attributes>[^>]*)>/i', $source, $tables);

            foreach ($tables['attributes'] as $attributes) {
                if (str_contains($attributes, 'event-info-table')) {
                    continue;
                }

                $hasClientPagination = str_contains($attributes, 'data-client-pagination');
                $hasServerPagination = preg_match(
                    '/\$(?:pagination|[A-Za-z]+Pagination)\[\'links\'\]/',
                    $source
                ) === 1;

                self::assertTrue(
                    $hasClientPagination || $hasServerPagination,
                    $view->getPathname() . ' contains a record table without pagination.'
                );
            }
        }
    }

    public function testClientPaginationIncludesAdvancedNavigationControls(): void
    {
        $layout = file_get_contents(dirname(__DIR__) . '/views/layouts/app.php');

        self::assertIsString($layout);
        self::assertStringContainsString("button('«« ' + labels.first", $layout);
        self::assertStringContainsString("button('−5', labels.previousFivePages", $layout);
        self::assertStringContainsString("'+5',", $layout);
        self::assertStringContainsString("labels.last + ' »»'", $layout);
    }

    public function testEveryInformationalTableHeaderUsesSharedSorting(): void
    {
        $root = dirname(__DIR__);
        $layout = file_get_contents($root . '/views/layouts/app.php');
        $css = file_get_contents($root . '/public/assets/css/app.css');

        self::assertIsString($layout);
        self::assertIsString($css);
        self::assertStringContainsString(
            "querySelectorAll('table:not(.event-info-table)')",
            $layout
        );
        self::assertStringContainsString("header.dataset.sortable === 'false'", $layout);
        self::assertStringContainsString("header.setAttribute('aria-sort', 'none')", $layout);
        self::assertStringContainsString("table.dispatchEvent(new CustomEvent('table:sorted'))", $layout);
        self::assertStringContainsString("table.dataset.sortMode === 'server'", $layout);
        self::assertStringContainsString("target.searchParams.delete(pageParameter)", $layout);
        self::assertStringContainsString('.table-sort-button', $css);
        self::assertStringContainsString('.table-sort-indicator', $css);

        foreach (
            [
                'views/admin/manage_clubs.php',
                'views/admin/manage_events.php',
                'views/club/_athlete_csv_tools.php',
                'views/club/area_add.php',
                'views/club/area_list.php',
                'views/events/register.php',
            ] as $template
        ) {
            $source = file_get_contents($root . '/' . $template);
            self::assertIsString($source);
            self::assertStringContainsString('data-sortable="false"', $source, $template);
        }

        foreach (
            [
                'views/admin/_event_enrolled_athletes.php',
                'views/admin/club_athletes.php',
                'views/admin/manage_clubs.php',
                'views/admin/manage_events.php',
                'views/club/area_add.php',
                'views/club/area_list.php',
                'views/club/list.php',
                'views/events/_entries_athletes.php',
                'views/events/_entries_clubs.php',
                'views/events/_entries_current_club.php',
                'views/events/register.php',
            ] as $template
        ) {
            $source = file_get_contents($root . '/' . $template);
            self::assertIsString($source);
            self::assertStringContainsString('data-sort-mode="server"', $source, $template);
            self::assertStringContainsString('data-sort-key=', $source, $template);
        }
    }
}
