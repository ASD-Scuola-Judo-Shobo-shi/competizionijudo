<?php

declare(strict_types=1);

namespace Tests;

use App\Model\MigrationRunner;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MigrationRunnerTest extends TestCase
{
    public function testDdlImplicitCommitDoesNotAbortMigrationRecording(): void
    {
        $transactionActive = false;
        $recordedVersions = [];
        $database = $this->createMock(PDO::class);
        $migrationQuery = $this->createMock(PDOStatement::class);
        $migrationQuery->method('fetchAll')->willReturn([]);
        $recordStatement = $this->createMock(PDOStatement::class);
        $recordStatement->method('execute')->willReturnCallback(
            static function (?array $parameters = null) use (&$recordedVersions): bool {
                $recordedVersions[] = (string) ($parameters[0] ?? '');

                return true;
            }
        );

        $database->method('query')->willReturn($migrationQuery);
        $database->method('prepare')->willReturn($recordStatement);
        $database->method('beginTransaction')->willReturnCallback(
            static function () use (&$transactionActive): bool {
                $transactionActive = true;

                return true;
            }
        );
        $database->method('exec')->willReturnCallback(
            static function (string $sql) use (&$transactionActive): int|false {
                if ($transactionActive && preg_match('/^\s*(CREATE|ALTER)\b/i', $sql) === 1) {
                    $transactionActive = false;
                }

                return 0;
            }
        );
        $database->method('inTransaction')->willReturnCallback(
            static function () use (&$transactionActive): bool {
                return $transactionActive;
            }
        );
        $database->method('commit')->willReturnCallback(
            static function () use (&$transactionActive): bool {
                if (!$transactionActive) {
                    throw new RuntimeException('There is no active transaction.');
                }
                $transactionActive = false;

                return true;
            }
        );

        (new MigrationRunner($database))->run();

        self::assertContains(
            '20260628_000001_create_authentication_throttles.sql',
            $recordedVersions
        );
        self::assertCount(count(glob(base_path('migrations/*.sql')) ?: []), $recordedVersions);
    }
}
