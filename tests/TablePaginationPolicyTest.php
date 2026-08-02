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
}
