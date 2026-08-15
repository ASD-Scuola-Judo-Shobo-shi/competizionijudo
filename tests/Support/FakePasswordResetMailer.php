<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Service\PasswordResetMailer;
use RuntimeException;

final class FakePasswordResetMailer implements PasswordResetMailer
{
    /** @var list<array{recipient: string, reset_url: string}> */
    public array $sent = [];

    /** @var list<array{recipient: string, confirmation_url: string}> */
    public array $confirmationSent = [];

    /** @var list<array{recipient: string, club_name: string, club_email: string, admin_url: string}> */
    public array $newClubNotifications = [];

    /** @var list<array{recipient: string, subject: string, message: string}> */
    public array $registrationRecaps = [];

    public function __construct(private readonly bool $fail = false)
    {
    }

    public function sendResetLink(string $recipient, string $resetUrl): void
    {
        if ($this->fail) {
            throw new RuntimeException('Synthetic mail transport failure.');
        }

        $this->sent[] = [
            'recipient' => $recipient,
            'reset_url' => $resetUrl,
        ];
    }

    public function sendRegistrationConfirmationLink(string $recipient, string $confirmationUrl): void
    {
        if ($this->fail) {
            throw new RuntimeException('Synthetic mail transport failure.');
        }

        $this->confirmationSent[] = [
            'recipient' => $recipient,
            'confirmation_url' => $confirmationUrl,
        ];
    }

    public function sendNewClubRegistrationNotification(
        string $recipient,
        string $clubName,
        string $clubEmail,
        string $adminUrl
    ): void {
        if ($this->fail) {
            throw new RuntimeException('Synthetic mail transport failure.');
        }

        $this->newClubNotifications[] = [
            'recipient' => $recipient,
            'club_name' => $clubName,
            'club_email' => $clubEmail,
            'admin_url' => $adminUrl,
        ];
    }

    public function sendRegistrationRecap(string $recipient, string $subject, string $message): void
    {
        if ($this->fail) {
            throw new RuntimeException('Synthetic mail transport failure.');
        }

        $this->registrationRecaps[] = compact('recipient', 'subject', 'message');
    }
}
