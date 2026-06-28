<?php

declare(strict_types=1);

namespace App\Service;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

final class DatabasePasswordResetRepository implements PasswordResetRepository
{
    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $clock;

    /** @param null|Closure(): DateTimeImmutable $clock */
    public function __construct(private readonly PDO $database, ?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable(
            'now',
            new DateTimeZone('UTC')
        );
    }

    public function findValidEmail(string $tokenHash): ?string
    {
        $statement = $this->prepare(
            'SELECT clubs.email FROM password_reset_tokens '
            . 'INNER JOIN clubs ON clubs.id = password_reset_tokens.club_id '
            . 'WHERE password_reset_tokens.token_hash = ? '
            . 'AND password_reset_tokens.used = 0 '
            . 'AND password_reset_tokens.expires_at > ? '
            . 'LIMIT 1'
        );
        $statement->execute([$tokenHash, $this->now()]);
        $email = $statement->fetchColumn();

        return is_string($email) ? $email : null;
    }

    public function consume(string $tokenHash, string $passwordHash): bool
    {
        $this->database->beginTransaction();

        try {
            $candidate = $this->prepare(
                'SELECT id, club_id FROM password_reset_tokens '
                . 'WHERE token_hash = ? AND used = 0 AND expires_at > ? '
                . 'LIMIT 1 FOR UPDATE'
            );
            $candidate->execute([$tokenHash, $this->now()]);
            $token = $candidate->fetch(PDO::FETCH_ASSOC);

            if (!is_array($token)) {
                $this->database->commit();

                return false;
            }

            $claim = $this->prepare(
                'UPDATE password_reset_tokens SET used = 1 '
                . 'WHERE id = ? AND used = 0 AND expires_at > ?'
            );
            $claim->execute([(int) $token['id'], $this->now()]);

            if ($claim->rowCount() !== 1) {
                $this->database->rollBack();

                return false;
            }

            $this->updatePasswordAndInvalidate((int) $token['club_id'], $passwordHash);
            $this->database->commit();

            return true;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }

    public function replacePassword(int $clubId, string $passwordHash): void
    {
        $this->database->beginTransaction();

        try {
            $this->updatePasswordAndInvalidate($clubId, $passwordHash);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }

    private function updatePasswordAndInvalidate(int $clubId, string $passwordHash): void
    {
        $password = $this->prepare('UPDATE clubs SET password_hash = ? WHERE id = ?');
        $password->execute([$passwordHash, $clubId]);
        if ($password->rowCount() !== 1) {
            throw new RuntimeException('Unable to update club credentials.');
        }

        $tokens = $this->prepare(
            'UPDATE password_reset_tokens SET used = 1 WHERE club_id = ? AND used = 0'
        );
        $tokens->execute([$clubId]);
    }

    private function now(): string
    {
        return ($this->clock)()
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->database->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare password reset query.');
        }

        return $statement;
    }
}
