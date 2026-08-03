<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\SessionPrincipal;

interface SessionCredentialValidator
{
    public function isCurrent(SessionPrincipal $principal, ?string $fingerprint): bool;
}
