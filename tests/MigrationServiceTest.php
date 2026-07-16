<?php

declare(strict_types=1);

namespace Tests;

use App\Model\MigrationException;
use App\Service\AutomaticMigrationSafety;
use App\Service\MigrationExecutor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MigrationServiceTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/competizionijudo-migration-safety-'
            . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testSafetyAllowsBaselineAndAdditiveMigration(): void
    {
        file_put_contents($this->directory . '/20260630_000000_create_schema.sql', 'BROKEN SQL;');
        file_put_contents(
            $this->directory . '/20260716_000001_safe.sql',
            "-- comment\nCREATE TABLE IF NOT EXISTS synthetic_safe (id INT PRIMARY KEY);\n"
        );

        (new AutomaticMigrationSafety())->assertSafe($this->directory);

        self::assertTrue(true);
    }

    public function testSafetyRejectsUnavailableOrUnsafeMigrationDirectory(): void
    {
        $safety = new AutomaticMigrationSafety();
        try {
            $safety->assertSafe($this->directory . '/missing');
            self::fail('Expected unavailable migration directory to fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Migration directory is unavailable.', $exception->getMessage());
        }

        file_put_contents($this->directory . '/20260716_000002_unsafe.sql', 'ALTER TABLE synthetic_safe ADD COLUMN label VARCHAR(20);');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('20260716_000002_unsafe.sql');
        $safety->assertSafe($this->directory);
    }

    public function testFailureDetailRedactsDatabasePasswordAndUnwrapsMigrationFailure(): void
    {
        $previousPassword = getenv('DB_PASS');
        putenv('DB_PASS=synthetic-secret');
        try {
            $detail = (new MigrationExecutor())->failureDetail(
                new MigrationException('20260716_000001_safe.sql', new RuntimeException('password=synthetic-secret'))
            );
        } finally {
            $previousPassword === false ? putenv('DB_PASS') : putenv('DB_PASS=' . $previousPassword);
        }

        self::assertSame('Migration diagnostic (RuntimeException): password=[redacted]', $detail);
        self::assertStringNotContainsString('synthetic-secret', $detail);
    }

    public function testFailureDetailSuppliesAFallbackForAnEmptyMessage(): void
    {
        $detail = (new MigrationExecutor())->failureDetail(new RuntimeException(''));

        self::assertSame('Migration diagnostic (RuntimeException): No diagnostic message was provided.', $detail);
    }
}
