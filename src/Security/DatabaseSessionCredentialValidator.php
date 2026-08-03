<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\AuthContext;
use App\Core\SessionPrincipal;
use App\Model\Club;

final class DatabaseSessionCredentialValidator implements SessionCredentialValidator
{
    public function isCurrent(SessionPrincipal $principal, ?string $fingerprint): bool
    {
        if ($fingerprint === null) {
            return false;
        }

        $current = match ($principal->type) {
            AuthContext::CLUB => $principal->clubId === null
                ? null
                : Club::credentialFingerprintById($principal->clubId),
            AuthContext::ADMINISTRATOR => $this->administratorFingerprint(),
            default => null,
        };

        return is_string($current) && hash_equals($current, $fingerprint);
    }

    private function administratorFingerprint(): ?string
    {
        $username = env('ADMIN_USER');
        $passwordHash = env('ADMIN_PASS_HASH');
        if (!is_string($username) || $username === '' || !is_string($passwordHash) || $passwordHash === '') {
            return null;
        }

        return CredentialFingerprint::forAdministrator($username, $passwordHash);
    }
}
