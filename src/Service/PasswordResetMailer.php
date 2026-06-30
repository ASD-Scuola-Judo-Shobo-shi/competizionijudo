<?php

declare(strict_types=1);

namespace App\Service;

interface PasswordResetMailer
{
    public function sendResetLink(string $recipient, string $resetUrl): void;
}
