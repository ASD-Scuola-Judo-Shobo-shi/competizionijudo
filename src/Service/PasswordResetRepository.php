<?php

declare(strict_types=1);

namespace App\Service;

interface PasswordResetRepository
{
    public function findValidEmail(string $tokenHash): ?string;

    public function consume(string $tokenHash, string $passwordHash): bool;

    public function replacePassword(int $clubId, string $passwordHash): void;
}
