<?php

declare(strict_types=1);

namespace Tests;

use App\Model\MigrationException;
use App\Service\MigrationExecutor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MigrationServiceTest extends TestCase
{
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
