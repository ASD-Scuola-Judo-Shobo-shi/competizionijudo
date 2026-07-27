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
            'Content-Transfer-Encoding' => 'quoted-printable',
        ];
        $sent = ($this->send)(
            $recipient,
            $this->encodeSubject(__('club.reset_email.subject')),
            $this->encodeBody(__('club.reset_email.body', ['url' => $resetUrl])),
            $headers
        );
        if (!$sent) {
            throw new RuntimeException('Aruba PHP mail rejected the password reset message.');
        }
    }

    public function sendRegistrationConfirmationLink(string $recipient, string $confirmationUrl): void
    {
        $sender = trim((string) env('MAIL_FROM_ADDRESS', ''));
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Registration confirmation recipient is invalid.');
        }
        if (filter_var($sender, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Registration confirmation sender is invalid.');
        }
        if (filter_var($confirmationUrl, FILTER_VALIDATE_URL) === false || !str_starts_with($confirmationUrl, 'https://')) {
            throw new RuntimeException('Registration confirmation URL must use HTTPS.');
        }

        $headers = [
            'From' => $sender,
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => 'quoted-printable',
        ];
        $sent = ($this->send)(
            $recipient,
            $this->encodeSubject(__('club.registration_confirmation_email.subject')),
            $this->encodeBody(__('club.registration_confirmation_email.body', ['url' => $confirmationUrl])),
            $headers
        );
        if (!$sent) {
            throw new RuntimeException('Aruba PHP mail rejected the registration confirmation message.');
        }
    }

    private function encodeSubject(string $subject): string
    {
        return mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
    }

    private function encodeBody(string $body): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $body);

        return quoted_printable_encode(str_replace("\n", "\r\n", $normalized));
    }
}
