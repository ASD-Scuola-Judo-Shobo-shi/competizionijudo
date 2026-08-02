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
        if (filter_var($resetUrl, FILTER_VALIDATE_URL) === false || !str_starts_with($resetUrl, 'https://')) {
            throw new RuntimeException('Password reset URL must use HTTPS.');
        }

        $this->sendPlainText(
            $recipient,
            __('club.reset_email.subject'),
            __('club.reset_email.body', ['url' => $resetUrl])
        );
    }

    public function sendRegistrationConfirmationLink(string $recipient, string $confirmationUrl): void
    {
        if (filter_var($confirmationUrl, FILTER_VALIDATE_URL) === false || !str_starts_with($confirmationUrl, 'https://')) {
            throw new RuntimeException('Registration confirmation URL must use HTTPS.');
        }

        $this->sendPlainText(
            $recipient,
            __('club.registration_confirmation_email.subject'),
            __('club.registration_confirmation_email.body', ['url' => $confirmationUrl])
        );
    }

    public function sendRegistrationRecap(string $recipient, string $subject, string $message): void
    {
        $this->sendPlainText($recipient, $subject, $message);
    }

    private function sendPlainText(string $recipient, string $subject, string $message): void
    {
        $sender = trim((string) env('MAIL_FROM_ADDRESS', ''));
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Mail recipient is invalid.');
        }
        if (filter_var($sender, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Mail sender is invalid.');
        }
        $subject = trim(preg_replace('/[\r\n]+/', ' ', $subject) ?? '');
        if ($subject === '' || trim($message) === '') {
            throw new RuntimeException('Mail subject and message are required.');
        }

        $headers = [
            'From' => $sender,
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => 'quoted-printable',
        ];
        $sent = ($this->send)(
            $recipient,
            $this->encodeSubject($subject),
            $this->encodeBody($message),
            $headers
        );
        if (!$sent) {
            throw new RuntimeException('Aruba PHP mail rejected the message.');
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
