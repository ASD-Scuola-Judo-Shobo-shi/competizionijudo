<?php

declare(strict_types=1);

namespace Tests;

use App\Model\MigrationException;
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
            '20260630_000000_create_schema.sql',
            $recordedVersions
        );
        self::assertCount(count(glob(base_path('migrations/*.sql')) ?: []), $recordedVersions);
    }

    public function testCompleteHistoricalChainAdoptsTheConsolidatedBaseline(): void
    {
        $historicalVersions = [
            '20260619_000000_create_baseline_schema.sql',
            '20260619_000001_copy_italian_columns_to_english.sql',
            '20260620_000001_create_password_reset_tokens.sql',
            '20260622_000001_make_location_required.sql',
            '20260623_000001_add_performance_indexes.sql',
            '20260628_000001_create_authentication_throttles.sql',
            '20260628_000002_add_normalized_club_email_unique_index.sql',
            '20260629_000001_add_athlete_weight_category.sql',
            '20260629_000002_add_list_query_indexes.sql',
            '20260629_000003_snapshot_closed_event_entries.sql',
        ];
        $migrationQuery = $this->createMock(PDOStatement::class);
        $migrationQuery->method('fetchAll')->willReturn($historicalVersions);
        $recordedVersions = [];
        $recordStatement = $this->createMock(PDOStatement::class);
        $recordStatement->expects(self::once())
            ->method('execute')
            ->willReturnCallback(
                static function (?array $parameters = null) use (&$recordedVersions): bool {
                    $recordedVersions[] = (string) ($parameters[0] ?? '');

                    return true;
                }
            );
        $deleteStatement = $this->createMock(PDOStatement::class);
        $deleteStatement->expects(self::once())
            ->method('execute')
            ->with($historicalVersions)
            ->willReturn(true);
        $database = $this->createMock(PDO::class);
        $database->expects(self::once())->method('query')->willReturn($migrationQuery);
        $database->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($recordStatement, $deleteStatement);
        $database->expects(self::once())->method('exec');
        $database->expects(self::once())->method('beginTransaction')->willReturn(true);
        $database->expects(self::once())->method('commit')->willReturn(true);

        (new MigrationRunner($database))->run();

        self::assertSame(['20260630_000000_create_schema.sql'], $recordedVersions);
    }

    public function testIncompleteHistoricalChainFailsBeforeChangingTheSchema(): void
    {
        $migrationQuery = $this->createMock(PDOStatement::class);
        $migrationQuery->method('fetchAll')->willReturn([
            '20260619_000000_create_baseline_schema.sql',
        ]);
        $database = $this->createMock(PDO::class);
        $database->expects(self::once())->method('query')->willReturn($migrationQuery);
        $database->expects(self::once())->method('exec');
        $database->expects(self::never())->method('prepare');
        $database->expects(self::never())->method('beginTransaction');

        try {
            (new MigrationRunner($database))->run();
            self::fail('Expected incomplete pre-squash history to fail.');
        } catch (MigrationException $exception) {
            self::assertSame('20260630_000000_create_schema.sql', $exception->version());
            self::assertSame(
                'Migration failed: 20260630_000000_create_schema.sql',
                $exception->getMessage()
            );
        }
    }

    public function testFailingStatementLeavesMigrationPendingAndReportsOnlyItsVersion(): void
    {
        $directory = sys_get_temp_dir() . '/competizionijudo-migration-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        $version = '20260629_999999_injected_failure.sql';
        $path = $directory . '/' . $version;
        self::assertNotFalse(file_put_contents(
            $path,
            "CREATE TABLE synthetic_migration (id INT);\nBROKEN SYNTHETIC STATEMENT;\n"
        ));

        $transactionActive = false;
        $migrationQuery = $this->createMock(PDOStatement::class);
        $migrationQuery->method('fetchAll')->willReturn([]);
        $database = $this->createMock(PDO::class);
        $database->expects(self::once())->method('query')->willReturn($migrationQuery);
        $database->expects(self::never())->method('prepare');
        $database->method('beginTransaction')->willReturnCallback(
            static function () use (&$transactionActive): bool {
                $transactionActive = true;

                return true;
            }
        );
        $database->method('exec')->willReturnCallback(
            static function (string $sql): int {
                if ($sql === 'BROKEN SYNTHETIC STATEMENT') {
                    throw new RuntimeException('Synthetic internal database detail.');
                }

                return 0;
            }
        );
        $database->method('inTransaction')->willReturnCallback(
            static function () use (&$transactionActive): bool {
                return $transactionActive;
            }
        );
        $database->expects(self::once())
            ->method('rollBack')
            ->willReturnCallback(
                static function () use (&$transactionActive): bool {
                    $transactionActive = false;

                    return true;
                }
            );

        try {
            (new MigrationRunner($database, $directory))->run();
            self::fail('Expected the injected migration statement to fail.');
        } catch (MigrationException $exception) {
            self::assertSame($version, $exception->version());
            self::assertSame('Migration failed: ' . $version, $exception->getMessage());
            self::assertStringNotContainsString('internal database detail', $exception->getMessage());
        } finally {
            unlink($path);
            rmdir($directory);
        }
    }
}
