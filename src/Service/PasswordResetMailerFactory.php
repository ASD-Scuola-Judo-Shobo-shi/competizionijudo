<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class PasswordResetMailerFactory
{
    public static function fromEnvironment(): PasswordResetMailer
    {
        return match (strtolower(trim((string) env('PASSWORD_RESET_MAILER', '')))) {
            'aruba' => new ArubaPhpMailPasswordResetMailer(),
            default => throw new RuntimeException('Configured password reset mailer is unsupported.'),
        };
    }
}
