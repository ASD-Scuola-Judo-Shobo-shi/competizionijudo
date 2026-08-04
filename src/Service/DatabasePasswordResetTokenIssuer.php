<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Club;
use App\Model\Database;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

final class DatabasePasswordResetTokenIssuer implements PasswordResetTokenIssuer
{
    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $clock;

    /** @param null|Closure(): DateTimeImmutable $clock */
    public function __construct(private readonly ?PDO $database = null, ?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable(
            'now',
            new DateTimeZone('UTC')
        );
    }

    public function issueForEmail(string $email): ?string
    {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = ($this->clock)()
            ->setTimezone(new DateTimeZone('UTC'))
            ->modify('+1 hour')
            ->format('Y-m-d H:i:s');

        $database = $this->database ?? Database::connection();
        $database->beginTransaction();

        try {
            $lock = $database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $club = $this->prepare(
                $database,
                'SELECT id FROM clubs WHERE normalized_email = ? LIMIT 1' . $lock
            );
            $club->execute([Club::normalizeEmail($email)]);
            $clubId = $club->fetchColumn();
            if ($clubId === false) {
                $database->commit();

                return null;
            }

            $deletePrevious = $this->prepare(
                $database,
                'DELETE FROM password_reset_tokens WHERE club_id = ?'
            );
            $deletePrevious->execute([(int) $clubId]);

            $insert = $this->prepare(
                $database,
                'INSERT INTO password_reset_tokens (club_id, token_hash, expires_at) VALUES (?, ?, ?)'
            );
            $insert->execute([(int) $clubId, $tokenHash, $expiresAt]);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }

            throw $exception;
        }

        return $rawToken;
    }

    private function prepare(PDO $database, string $sql): PDOStatement
    {
        $statement = $database->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare password reset issuance query.');
        }

        return $statement;
    }
}
