<?php

declare(strict_types=1);

namespace App\Service;

interface PasswordResetTokenIssuer
{
    public function issueForEmail(string $email): ?string;
}
