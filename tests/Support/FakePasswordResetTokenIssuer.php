<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Service\PasswordResetTokenIssuer;

final class FakePasswordResetTokenIssuer implements PasswordResetTokenIssuer
{
    /** @var list<string> */
    public array $requestedEmails = [];

    public function __construct(private readonly ?string $token)
    {
    }

    public function issueForEmail(string $email): ?string
    {
        $this->requestedEmails[] = $email;

        return $this->token;
    }
}
