<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class ViewDataAccessTest extends TestCase
{
    public function testApplicationLayoutContainsNoSessionModelDatabaseOrConfigAccess(): void
    {
        $layout = (string) file_get_contents(dirname(__DIR__) . '/views/layouts/app.php');

        self::assertStringNotContainsString('Session::', $layout);
        self::assertStringNotContainsString('Database::', $layout);
        self::assertStringNotContainsString('App\\Model', $layout);
        self::assertStringNotContainsString('config(', $layout);
        self::assertStringNotContainsString('$_GET', $layout);
    }

    public function testTemplatesContainNoModelDataAccessCalls(): void
    {
        $templates = '';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__) . '/views')
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $templates .= (string) file_get_contents($file->getPathname());
            }
        }

        foreach (['Database::', 'Session::', 'Club::find', 'Entry::find', 'Athlete::find', 'Event::find'] as $call) {
            self::assertStringNotContainsString($call, $templates);
        }
    }
}
