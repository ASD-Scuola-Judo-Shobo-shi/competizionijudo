<?php

declare(strict_types=1);

namespace Tests;

use App\Core\FileLogger;
use App\Core\ProductionConfiguration;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProductionConfigurationTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        $this->logPath = sys_get_temp_dir() . '/competizionijudo-configuration-'
            . bin2hex(random_bytes(8)) . '.log';
    }

    protected function tearDown(): void
    {
        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }
    }

    public function testCompleteProductionConfigurationPassesWithoutLogging(): void
    {
        ProductionConfiguration::assertReady(new FileLogger($this->logPath), $this->validConfiguration());

        self::assertFileDoesNotExist($this->logPath);
    }

    public function testMissingProductionValueFailsWithSafeActionableLog(): void
    {
        $configuration = $this->validConfiguration();
        $configuration['DB_PASS'] = 'SENSITIVE-DATABASE-PASSWORD';
        unset($configuration['DB_NAME']);

        try {
            ProductionConfiguration::assertReady(new FileLogger($this->logPath), $configuration);
            self::fail('Missing production configuration did not fail startup.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Production configuration is invalid. Review the application log.',
                $exception->getMessage()
            );
        }

        $log = file_get_contents($this->logPath);
        self::assertIsString($log);
        self::assertStringContainsString('"event":"configuration.missing.db_name"', $log);
        self::assertStringNotContainsString('SENSITIVE-DATABASE-PASSWORD', $log);
    }

    public function testDebugModeIsRejectedForProductionStartup(): void
    {
        $configuration = $this->validConfiguration();
        $configuration['APP_DEBUG'] = 'true';

        $this->expectException(RuntimeException::class);

        ProductionConfiguration::assertReady(new FileLogger($this->logPath), $configuration);
    }

    public function testInvalidPrivacyContactAndRetentionAreRejected(): void
    {
        $configuration = $this->validConfiguration();
        $configuration['APP_OWNER_EMAIL'] = 'not-an-email';
        $configuration['APP_LOG_RETENTION_DAYS'] = '0';

        $this->expectException(RuntimeException::class);

        ProductionConfiguration::assertReady(new FileLogger($this->logPath), $configuration);
    }

    public function testUnsupportedMailerAndInvalidSenderAreRejected(): void
    {
        $configuration = $this->validConfiguration();
        $configuration['PASSWORD_RESET_MAILER'] = 'unsupported';
        $configuration['MAIL_FROM_ADDRESS'] = 'not-an-email';

        $this->expectException(RuntimeException::class);

        ProductionConfiguration::assertReady(new FileLogger($this->logPath), $configuration);
    }

    public function testNonHttpsApplicationUrlIsRejected(): void
    {
        $configuration = $this->validConfiguration();
        $configuration['APP_URL'] = 'http://example.test';

        $this->expectException(RuntimeException::class);

        ProductionConfiguration::assertReady(new FileLogger($this->logPath), $configuration);
    }

    /** @return array<string, string> */
    private function validConfiguration(): array
    {
        return [
            'APP_URL' => 'https://example.test',
            'APP_DEBUG' => 'false',
            'DB_HOST' => '127.0.0.1',
            'DB_NAME' => 'synthetic_database',
            'DB_USER' => 'synthetic_user',
            'DB_PASS' => 'synthetic-password',
            'ADMIN_USER' => 'synthetic-admin',
            'ADMIN_PASS_HASH' => 'synthetic-password-hash',
            'PASSWORD_RESET_MAILER' => 'aruba',
            'MAIL_FROM_ADDRESS' => 'postmaster@example.test',
            'APP_OWNER' => 'Synthetic Sports Association',
            'APP_OWNER_ADDRESS' => '1 Test Street, Test City',
            'APP_OWNER_FISCAL_CODE' => 'SYNTHETIC-FISCAL-CODE',
            'APP_OWNER_EMAIL' => 'privacy@example.test',
            'APP_WEBHOST' => 'Synthetic Hosting Ltd',
            'APP_WEBHOST_LOCATION' => 'European Union',
            'APP_LOG_RETENTION_DAYS' => '30',
            'APP_BACKUP_RETENTION_DAYS' => '30',
        ];
    }
}
