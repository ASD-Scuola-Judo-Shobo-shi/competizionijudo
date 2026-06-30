<?php

declare(strict_types=1);

namespace Tests;

use App\Service\ArubaPhpMailPasswordResetMailer;
use App\Service\PasswordResetMailerFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PasswordResetMailerFactoryTest extends TestCase
{
    private bool $driverExisted;
    private mixed $originalDriver;

    protected function setUp(): void
    {
        $this->driverExisted = array_key_exists('PASSWORD_RESET_MAILER', $_ENV);
        $this->originalDriver = $_ENV['PASSWORD_RESET_MAILER'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->driverExisted) {
            $_ENV['PASSWORD_RESET_MAILER'] = $this->originalDriver;
        } else {
            unset($_ENV['PASSWORD_RESET_MAILER']);
        }
    }

    public function testBuildsTheExplicitlyConfiguredArubaAdapter(): void
    {
        $_ENV['PASSWORD_RESET_MAILER'] = 'aruba';

        self::assertInstanceOf(
            ArubaPhpMailPasswordResetMailer::class,
            PasswordResetMailerFactory::fromEnvironment()
        );
    }

    public function testRejectsUnknownProvidersInsteadOfSilentlyFallingBack(): void
    {
        $_ENV['PASSWORD_RESET_MAILER'] = 'synthetic-provider';

        $this->expectException(RuntimeException::class);
        PasswordResetMailerFactory::fromEnvironment();
    }
}
