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
        ];
    }
}
