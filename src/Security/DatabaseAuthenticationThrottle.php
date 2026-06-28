<?php

declare(strict_types=1);

namespace App\Security;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

final class DatabaseAuthenticationThrottle implements AuthenticationThrottle
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 300;
    private const BLOCK_SECONDS = 300;
    private const RETENTION_SECONDS = 86400;
    private const CLEANUP_LIMIT = 100;

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

    public function isBlocked(string $scope, string $account, string $networkSignal): bool
    {
        $statement = $this->prepare(
            'SELECT blocked_until FROM authentication_throttles WHERE throttle_key = ?'
        );
        $statement->execute([$this->key($scope, $account, $networkSignal)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row) || !is_string($row['blocked_until'] ?? null)) {
            return false;
        }

        return $this->date($row['blocked_until']) > $this->now();
    }

    public function recordAttempt(string $scope, string $account, string $networkSignal): void
    {
        $now = $this->now();
        $key = $this->key($scope, $account, $networkSignal);
        $this->database->beginTransaction();

        try {
            $insert = $this->prepare(
                'INSERT INTO authentication_throttles '
                . '(throttle_key, attempt_count, window_started_at, blocked_until, updated_at) '
                . 'VALUES (?, 0, ?, NULL, ?) '
                . 'ON DUPLICATE KEY UPDATE throttle_key = throttle_key'
            );
            $formattedNow = $this->format($now);
            $insert->execute([$key, $formattedNow, $formattedNow]);

            $statement = $this->prepare(
                'SELECT attempt_count, window_started_at, blocked_until '
                . 'FROM authentication_throttles WHERE throttle_key = ? FOR UPDATE'
            );
            $statement->execute([$key]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                throw new RuntimeException('Authentication throttle row was not persisted.');
            }

            $this->update($key, $row, $now);

            $cleanup = $this->prepare(
                'DELETE FROM authentication_throttles WHERE updated_at < ? LIMIT '
                . self::CLEANUP_LIMIT
            );
            $cleanup->execute([$this->format($now->modify('-' . self::RETENTION_SECONDS . ' seconds'))]);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }

    public function clear(string $scope, string $account, string $networkSignal): void
    {
        $statement = $this->prepare(
            'DELETE FROM authentication_throttles WHERE throttle_key = ?'
        );
        $statement->execute([$this->key($scope, $account, $networkSignal)]);
    }

    /** @param array<string, mixed> $row */
    private function update(string $key, array $row, DateTimeImmutable $now): void
    {
        $windowStartedAt = $this->date((string) $row['window_started_at']);
        $blockedUntil = is_string($row['blocked_until'] ?? null)
            ? $this->date($row['blocked_until'])
            : null;
        $windowExpired = $windowStartedAt <= $now->modify('-' . self::WINDOW_SECONDS . ' seconds');
        $blockExpired = $blockedUntil !== null && $blockedUntil <= $now;

        if ($windowExpired || $blockExpired) {
            $attemptCount = 1;
            $windowStartedAt = $now;
            $blockedUntil = null;
        } else {
            $attemptCount = (int) $row['attempt_count'] + 1;
            if ($attemptCount >= self::MAX_ATTEMPTS) {
                $blockedUntil = $now->modify('+' . self::BLOCK_SECONDS . ' seconds');
            }
        }

        $statement = $this->prepare(
            'UPDATE authentication_throttles '
            . 'SET attempt_count = ?, window_started_at = ?, blocked_until = ?, updated_at = ? '
            . 'WHERE throttle_key = ?'
        );
        $statement->execute([
            $attemptCount,
            $this->format($windowStartedAt),
            $blockedUntil === null ? null : $this->format($blockedUntil),
            $this->format($now),
            $key,
        ]);
    }

    private function key(string $scope, string $account, string $networkSignal): string
    {
        return hash('sha256', implode("\0", [
            strtolower(trim($scope)),
            strtolower(trim($account)),
            trim($networkSignal),
        ]));
    }

    private function now(): DateTimeImmutable
    {
        return ($this->clock)()->setTimezone(new DateTimeZone('UTC'));
    }

    private function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    private function format(DateTimeImmutable $value): string
    {
        return $value->format('Y-m-d H:i:s');
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->database->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare authentication throttle query.');
        }

        return $statement;
    }
}
