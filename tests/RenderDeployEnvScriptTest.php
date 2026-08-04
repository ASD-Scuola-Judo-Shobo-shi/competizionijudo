<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class RenderDeployEnvScriptTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/competizionijudo-render-env-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (scandir($this->directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $this->directory . '/' . $entry;
            if (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($this->directory);
    }

    public function testRendersTemplateWithGitHubEnvironmentOverrides(): void
    {
        [$status, $output] = $this->runScript([
            'APP_ENV' => 'development',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://dev.example.test',
            'DB_HOST' => '127.0.0.1',
            'DB_NAME' => 'competizionijudo_dev',
            'DB_USER' => 'competizionijudo_dev',
            'DB_PASS' => 'dev-secret',
            'ADMIN_USER' => 'dev-admin',
            'ADMIN_PASS_HASH' => '$2y$12$abcdefghijklmnopqrstuvABCDEFGHIJKLMNOpqrstuvwxyz01234',
            'MIGRATIONS_TOKEN' => 'synthetic-migration-token',
            'MAIL_FROM_ADDRESS' => 'postmaster@dev.example.test',
            'APP_OWNER' => 'Synthetic Sports Association',
            'APP_OWNER_ADDRESS' => '1 Test Street, Test City',
            'APP_OWNER_FISCAL_CODE' => 'SYNTHETIC-FISCAL-CODE',
            'APP_OWNER_EMAIL' => 'privacy@synthetic.test',
            'APP_WEBHOST' => 'Synthetic Hosting Ltd',
            'APP_WEBHOST_LOCATION' => 'European Union',
            'APP_LOG_RETENTION_DAYS' => '30',
            'APP_BACKUP_RETENTION_DAYS' => '30',
            'ATHLETE_DUPLICATE_MAINTENANCE' => 'true',
            'CLUB_ATHLETE_LIMIT' => '250',
            'CLUB_ENTRY_LIMIT' => '0',
        ]);

        self::assertSame(0, $status, $output);
        self::assertStringContainsString('Rendered deploy environment:', $output);

        $contents = (string) file_get_contents($this->directory . '/.env');
        self::assertStringContainsString('APP_NAME="Competizioni Judo"', $contents);
        self::assertStringContainsString('APP_ENV=development', $contents);
        self::assertStringContainsString('APP_URL=https://dev.example.test', $contents);
        self::assertStringContainsString('DB_NAME=competizionijudo_dev', $contents);
        self::assertStringContainsString('PASSWORD_RESET_MAILER=aruba', $contents);
        self::assertStringContainsString('APP_OWNER=Synthetic Sports Association', $contents);
        self::assertStringContainsString('ATHLETE_DUPLICATE_MAINTENANCE=true', $contents);
        self::assertStringContainsString('CLUB_ATHLETE_LIMIT=250', $contents);
        self::assertStringContainsString('CLUB_ENTRY_LIMIT=0', $contents);
        self::assertSame(0600, fileperms($this->directory . '/.env') & 0777);
    }

    public function testRejectsInvalidClubQuotaValues(): void
    {
        [$status, $output] = $this->runScript([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://www.example.test',
            'DB_HOST' => '127.0.0.1',
            'DB_NAME' => 'competizionijudo',
            'DB_USER' => 'competizionijudo',
            'ADMIN_USER' => 'prod-admin',
            'ADMIN_PASS_HASH' => '$2y$12$abcdefghijklmnopqrstuvABCDEFGHIJKLMNOpqrstuvwxyz01234',
            'MIGRATIONS_TOKEN' => 'synthetic-migration-token',
            'MAIL_FROM_ADDRESS' => 'postmaster@example.test',
            'APP_OWNER' => 'Synthetic Sports Association',
            'APP_OWNER_ADDRESS' => '1 Test Street, Test City',
            'APP_OWNER_FISCAL_CODE' => 'SYNTHETIC-FISCAL-CODE',
            'APP_OWNER_EMAIL' => 'privacy@synthetic.test',
            'APP_WEBHOST' => 'Synthetic Hosting Ltd',
            'APP_WEBHOST_LOCATION' => 'European Union',
            'APP_LOG_RETENTION_DAYS' => '30',
            'APP_BACKUP_RETENTION_DAYS' => '30',
            'CLUB_ATHLETE_LIMIT' => 'unlimited',
            'CLUB_ENTRY_LIMIT' => '-1',
        ]);

        self::assertSame(1, $status, $output);
        self::assertStringContainsString(
            'CLUB_ATHLETE_LIMIT must be a non-negative integer (0 disables the quota).',
            $output
        );
        self::assertStringContainsString(
            'CLUB_ENTRY_LIMIT must be a non-negative integer (0 disables the quota).',
            $output
        );
        self::assertFileDoesNotExist($this->directory . '/.env');
    }

    public function testRejectsMissingRequiredValues(): void
    {
        [$status, $output] = $this->runScript([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://www.example.test',
            'DB_HOST' => '127.0.0.1',
            'DB_NAME' => 'competizionijudo',
            'DB_USER' => 'competizionijudo',
            'ADMIN_USER' => 'prod-admin',
            'ADMIN_PASS_HASH' => '$2y$12$abcdefghijklmnopqrstuvABCDEFGHIJKLMNOpqrstuvwxyz01234',
            'MIGRATIONS_TOKEN' => 'synthetic-migration-token',
            'MAIL_FROM_ADDRESS' => 'postmaster@example.test',
            'APP_OWNER' => 'Synthetic Sports Association',
            'APP_OWNER_ADDRESS' => '1 Test Street, Test City',
            'APP_OWNER_FISCAL_CODE' => 'SYNTHETIC-FISCAL-CODE',
            'APP_OWNER_EMAIL' => 'privacy@synthetic.test',
            'APP_WEBHOST' => 'Synthetic Hosting Ltd',
            'APP_WEBHOST_LOCATION' => 'European Union',
            'APP_LOG_RETENTION_DAYS' => '30',
            'APP_BACKUP_RETENTION_DAYS' => '30',
        ]);

        self::assertSame(1, $status, $output);
        self::assertStringContainsString('Missing required deploy env keys: DB_PASS', $output);
        self::assertFileDoesNotExist($this->directory . '/.env');
    }

    /**
     * @param array<string, string> $environment
     * @return array{int, string}
     */
    private function runScript(array $environment): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/scripts/render-deploy-env.php', $this->directory],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__),
            $environment
        );
        self::assertIsResource($process);

        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }
}
