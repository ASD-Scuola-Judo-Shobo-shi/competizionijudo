<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use Throwable;

final class AccountWorkflowRetentionService
{
    public function __construct(private readonly PDO $database)
    {
    }

    /** @return array{registration_confirmations:int,password_reset_tokens:int} */
    public function purgeExpired(string $now): array
    {
        $this->database->beginTransaction();

        try {
            $confirmations = $this->database->prepare(
                'DELETE FROM club_registration_confirmations WHERE expires_at <= ?'
            );
            $confirmations->execute([$now]);

            $resetTokens = $this->database->prepare(
                'DELETE FROM password_reset_tokens WHERE expires_at <= ? OR used = 1'
            );
            $resetTokens->execute([$now]);

            $this->database->commit();

            return [
                'registration_confirmations' => $confirmations->rowCount(),
                'password_reset_tokens' => $resetTokens->rowCount(),
            ];
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }
}
