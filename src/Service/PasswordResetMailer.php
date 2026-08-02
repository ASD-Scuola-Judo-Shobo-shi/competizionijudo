<?php

declare(strict_types=1);

namespace App\Service;

interface PasswordResetMailer
{
    public function sendResetLink(string $recipient, string $resetUrl): void;

    public function sendRegistrationConfirmationLink(string $recipient, string $confirmationUrl): void;

    public function sendRegistrationRecap(string $recipient, string $subject, string $message): void;
}
