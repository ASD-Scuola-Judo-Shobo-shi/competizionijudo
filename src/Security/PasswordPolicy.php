<?php

declare(strict_types=1);

namespace App\Security;

final class PasswordPolicy
{
    /** Minimum number of Unicode characters required whenever a password is set. */
    public const MINIMUM_LENGTH = 12;

    private function __construct()
    {
    }

    public static function accepts(string $password): bool
    {
        return mb_strlen($password) >= self::MINIMUM_LENGTH;
    }
}
