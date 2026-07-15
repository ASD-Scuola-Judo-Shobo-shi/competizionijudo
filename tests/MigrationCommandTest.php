<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class MigrationCommandTest extends TestCase
{
    public function testMigrationCommandDoesNotBootstrapTheWebApplication(): void
    {
        $command = file_get_contents(dirname(__DIR__) . '/scripts/run-migrations.php');
        self::assertIsString($command);

        self::assertStringContainsString("require dirname(__DIR__) . '/vendor/autoload.php';", $command);
        self::assertStringContainsString("require dirname(__DIR__) . '/src/helpers.php';", $command);
        self::assertStringContainsString("load_env(dirname(__DIR__) . '/.env');", $command);
        self::assertStringNotContainsString("require dirname(__DIR__) . '/src/bootstrap.php';", $command);
    }
}
