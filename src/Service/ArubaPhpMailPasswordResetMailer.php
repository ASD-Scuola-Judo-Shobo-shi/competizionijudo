<?php

declare(strict_types=1);

namespace App\Service;

use Closure;
use RuntimeException;

final class ArubaPhpMailPasswordResetMailer implements PasswordResetMailer
{
    /** @var Closure(string, string, string, array<string, string>): bool */
    private readonly Closure $send;

    /** @param (Closure(string, string, string, array<string, string>): bool)|null $send */
    public function __construct(?Closure $send = null)
    {
        $this->send = $send ?? static fn(
            string $recipient,
            string $subject,
            string $message,
            array $headers
        ): bool => mail($recipient, $subject, $message, $headers);
    }

    public function sendResetLink(string $recipient, string $resetUrl): void
    {
        $sender = trim((string) env('MAIL_FROM_ADDRESS', ''));
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Password reset recipient is invalid.');
        }
        if (filter_var($sender, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Password reset sender is invalid.');
        }
        if (filter_var($resetUrl, FILTER_VALIDATE_URL) === false || !str_starts_with($resetUrl, 'https://')) {
            throw new RuntimeException('Password reset URL must use HTTPS.');
        }

        $headers = [
            'From' => $sender,
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
        ];
        $sent = ($this->send)(
            $recipient,
            __('club.reset_email.subject'),
            __('club.reset_email.body', ['url' => $resetUrl]),
            $headers
        );
        if (!$sent) {
            throw new RuntimeException('Aruba PHP mail rejected the password reset message.');
        }
    }
}
