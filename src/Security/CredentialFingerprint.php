<?php

declare(strict_types=1);

namespace App\Security;

final class CredentialFingerprint
{
    private function __construct()
    {
    }

    public static function forClubPasswordHash(string $passwordHash): string
    {
        return hash('sha256', "club\0" . $passwordHash);
    }

    public static function forAdministrator(string $username, string $passwordHash): string
    {
        return hash('sha256', "administrator\0" . $username . "\0" . $passwordHash);
    }
}
