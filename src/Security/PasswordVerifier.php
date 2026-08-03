<?php

declare(strict_types=1);

namespace App\Security;

final class PasswordVerifier
{
    /**
     * A valid hash for a password that is never accepted. It keeps unknown or
     * malformed identities on the same password-verification path as real ones.
     */
    private const DUMMY_HASH = '$2y$12$y16PQjTRvww7KLhAU3QVi.1TIefpeMduKDDlYs9dXN3zVyrDeoA8q';

    private function __construct()
    {
    }

    public static function matches(string $password, ?string $passwordHash): bool
    {
        $supportedHash = is_string($passwordHash)
            && (password_get_info($passwordHash)['algo'] ?? null) !== null;
        $matches = password_verify($password, $supportedHash ? $passwordHash : self::DUMMY_HASH);

        return $supportedHash && $matches;
    }
}
