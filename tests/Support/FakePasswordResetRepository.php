<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Service\PasswordResetRepository;

final class FakePasswordResetRepository implements PasswordResetRepository
{
    /** @var list<array{token_hash: string, password_hash: string}> */
    public array $consumed = [];

    /** @var list<array{club_id: int, password_hash: string}> */
    public array $replaced = [];

    public function __construct(
        private readonly ?string $validEmail = null,
        private readonly bool $consumeResult = false
    ) {
    }

    public function findValidEmail(string $tokenHash): ?string
    {
        return $this->validEmail;
    }

    public function consume(string $tokenHash, string $passwordHash): bool
    {
        $this->consumed[] = [
            'token_hash' => $tokenHash,
            'password_hash' => $passwordHash,
        ];

        return $this->consumeResult;
    }

    public function replacePassword(int $clubId, string $passwordHash): void
    {
        $this->replaced[] = [
            'club_id' => $clubId,
            'password_hash' => $passwordHash,
        ];
    }
}
