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

    public function testMigrationCommandReportsARedactedRootCauseBeforeApplyingAVersion(): void
    {
        $command = (string) file_get_contents(dirname(__DIR__) . '/scripts/run-migrations.php');

        self::assertStringContainsString('use App\\Service\\MigrationExecutor;', $command);
        self::assertStringContainsString('$executor = new MigrationExecutor();', $command);
        self::assertStringContainsString('$executor->run()', $command);
        self::assertStringContainsString('$executor->failureDetail($exception)', $command);
        self::assertStringContainsString('Migration failed before a version could be applied.', $command);
        self::assertSame(2, substr_count($command, 'failureDetail($exception)'));
    }

    public function testManualEventSchemaRepairIsBrowserOnlyAndRequiresBasicAuthentication(): void
    {
        $command = (string) file_get_contents(dirname(__DIR__) . '/scripts/repair-event-schema.php');

        self::assertStringContainsString("PHP_SAPI === 'cli'", $command);
        self::assertStringContainsString('$_SERVER[\'REQUEST_METHOD\'] !== \'POST\'', $command);
        self::assertStringContainsString('isHttpsRequest()', $command);
        self::assertStringContainsString('isAuthorizedAdministrator()', $command);
        self::assertStringContainsString('$_SERVER[\'PHP_AUTH_USER\']', $command);
        self::assertStringContainsString('$_SERVER[\'PHP_AUTH_PW\']', $command);
        self::assertStringContainsString("env('ADMIN_USER', '')", $command);
        self::assertStringContainsString('hash_equals($expectedUser, $providedPassword)', $command);
        self::assertStringContainsString('WWW-Authenticate: Basic realm=', $command);
        self::assertStringNotContainsString('HTTP_X_FORWARDED_PROTO', $command);
        self::assertStringContainsString("hash_equals('REPAIR EVENT SCHEMA', \$confirmation)", $command);
        self::assertStringContainsString('MAX_PARTICIPANTS_MIGRATION', $command);
        self::assertStringContainsString('REGISTRATION_EXCEPTIONS_MIGRATION', $command);
        self::assertStringContainsString('MigrationExecutor', $command);
        self::assertStringContainsString('schemaMigrationsTableExists()', $command);
        self::assertStringContainsString('schema_migrations is absent, so no migration history was recorded.', $command);
        self::assertStringContainsString('delete this temporary PHP file immediately.', $command);
        self::assertStringContainsString("__DIR__ . '/prod'", $command);
        self::assertStringNotContainsString('MIGRATION_WEBHOOK_SECRET', $command);
        self::assertStringNotContainsString('ADMIN_PASS_HASH', $command);
        self::assertStringNotContainsString("require dirname(__DIR__) . '/src/bootstrap.php';", $command);
    }
}
