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

    public function testAutomaticMigrationSafetyCheckAllowsOnlyRetryableTableCreation(): void
    {
        $directory = sys_get_temp_dir() . '/competizionijudo-automatic-migration-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));

        try {
            self::assertNotFalse(file_put_contents(
                $directory . '/20260716_000001_safe.sql',
                'CREATE TABLE IF NOT EXISTS synthetic_safe (id INT PRIMARY KEY);' . PHP_EOL
            ));
            self::assertSame([0, 'Automatic migration safety check passed.' . PHP_EOL], $this->runSafetyCheck($directory));

            self::assertNotFalse(file_put_contents(
                $directory . '/20260716_000002_unsafe.sql',
                'ALTER TABLE synthetic_safe ADD COLUMN label VARCHAR(20);' . PHP_EOL
            ));
            [$status, $output] = $this->runSafetyCheck($directory);
            self::assertSame(1, $status);
            self::assertStringContainsString('20260716_000002_unsafe.sql', $output);
        } finally {
            foreach (glob($directory . '/*.sql') ?: [] as $migration) {
                unlink($migration);
            }
            rmdir($directory);
        }
    }

    /** @return array{int, string} */
    private function runSafetyCheck(string $directory): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/scripts/check-automatic-migrations.php', $directory],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);

        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }
}
