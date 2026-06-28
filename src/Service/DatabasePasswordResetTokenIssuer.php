<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Club;
use App\Model\Database;
use DateTimeImmutable;
use DateTimeZone;

final class DatabasePasswordResetTokenIssuer implements PasswordResetTokenIssuer
{
    public function issueForEmail(string $email): ?string
    {
        $club = Club::findByEmail($email);
        if ($club === null) {
            return null;
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('+1 hour')
            ->format('Y-m-d H:i:s');

        $database = Database::connection();
        $database->prepare(
            'UPDATE password_reset_tokens SET used = 1 WHERE club_id = ? AND used = 0'
        )->execute([$club->id]);
        $database->prepare(
            'INSERT INTO password_reset_tokens (club_id, token_hash, expires_at) VALUES (?, ?, ?)'
        )->execute([$club->id, $tokenHash, $expiresAt]);

        return $rawToken;
    }
}
