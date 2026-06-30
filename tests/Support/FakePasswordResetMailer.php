<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Service\PasswordResetMailer;
use RuntimeException;

final class FakePasswordResetMailer implements PasswordResetMailer
{
    /** @var list<array{recipient: string, reset_url: string}> */
    public array $sent = [];

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
}
