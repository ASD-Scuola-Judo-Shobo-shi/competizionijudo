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
    private const RETENTION_SECONDS = 86400;
    private const CLEANUP_LIMIT = 100;

    /**
     * Pair limits stop focused guessing, account limits stop distributed
     * guessing, and network limits bound broad credential stuffing.
     *
     * @var array<string, array{attempts: int, window: int, block: int}>
     */
    private const LIMITS = [
        'pair' => ['attempts' => 5, 'window' => 300, 'block' => 300],
        'account' => ['attempts' => 10, 'window' => 900, 'block' => 900],
        'network' => ['attempts' => 100, 'window' => 300, 'block' => 900],
    ];

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

    public function consume(string $scope, string $account, string $networkSignal): bool
    {
        $now = $this->now();
        $dimensions = $this->dimensions($scope, $account, $networkSignal);
        usort(
            $dimensions,
            static fn(array $left, array $right): int => $left['key'] <=> $right['key']
        );
        $this->database->beginTransaction();

        try {
            $formattedNow = $this->format($now);
            $locked = [];
            foreach ($dimensions as $dimension) {
                $insert = $this->prepare(
                    'INSERT INTO authentication_throttles '
                    . '(throttle_key, attempt_count, window_started_at, blocked_until, updated_at) '
                    . 'VALUES (?, 0, ?, NULL, ?) '
                    . $this->insertConflictClause()
                );
                $insert->execute([$dimension['key'], $formattedNow, $formattedNow]);

                $statement = $this->prepare(
                    'SELECT attempt_count, window_started_at, blocked_until '
                    . 'FROM authentication_throttles WHERE throttle_key = ? FOR UPDATE'
                );
                $statement->execute([$dimension['key']]);
                $row = $statement->fetch(PDO::FETCH_ASSOC);

                if (!is_array($row)) {
                    throw new RuntimeException('Authentication throttle row was not persisted.');
                }

                $locked[] = ['dimension' => $dimension, 'row' => $row];
            }

            foreach ($locked as $candidate) {
                $blockedUntil = $candidate['row']['blocked_until'] ?? null;
                if (is_string($blockedUntil) && $this->date($blockedUntil) > $now) {
                    $this->database->commit();

                    return false;
                }
            }

            foreach ($locked as $candidate) {
                $this->update(
                    $candidate['dimension']['key'],
                    $candidate['row'],
                    $candidate['dimension']['limit'],
                    $now
                );
            }

            $cleanup = $this->prepare(
                'DELETE FROM authentication_throttles WHERE updated_at < ? LIMIT '
                . self::CLEANUP_LIMIT
            );
            $cleanup->execute([$this->format($now->modify('-' . self::RETENTION_SECONDS . ' seconds'))]);
            $this->database->commit();

            return true;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }

    public function clear(string $scope, string $account, string $networkSignal): void
    {
        $dimensions = $this->dimensions($scope, $account, $networkSignal);
        $keys = [];
        foreach ($dimensions as $dimension) {
            if ($dimension['name'] !== 'network') {
                $keys[] = $dimension['key'];
            }
        }

        $statement = $this->prepare(
            'DELETE FROM authentication_throttles WHERE throttle_key IN (?, ?)'
        );
        $statement->execute($keys);
    }

    /**
     * @param array<string, mixed> $row
     * @param array{attempts: int, window: int, block: int} $limit
     */
    private function update(
        string $key,
        array $row,
        array $limit,
        DateTimeImmutable $now
    ): void {
        $windowStartedAt = $this->date((string) $row['window_started_at']);
        $blockedUntil = is_string($row['blocked_until'] ?? null)
            ? $this->date($row['blocked_until'])
            : null;
        $windowExpired = $windowStartedAt <= $now->modify('-' . $limit['window'] . ' seconds');
        $blockExpired = $blockedUntil !== null && $blockedUntil <= $now;

        if ($windowExpired || $blockExpired) {
            $attemptCount = 1;
            $windowStartedAt = $now;
            $blockedUntil = null;
        } else {
            $attemptCount = (int) $row['attempt_count'] + 1;
            if ($attemptCount >= $limit['attempts']) {
                $blockedUntil = $now->modify('+' . $limit['block'] . ' seconds');
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

    /**
     * @return list<array{
     *     name: string,
     *     key: string,
     *     limit: array{attempts: int, window: int, block: int}
     * }>
     */
    private function dimensions(string $scope, string $account, string $networkSignal): array
    {
        $scope = strtolower(trim($scope));
        $account = strtolower(trim($account));
        $networkSignal = trim($networkSignal);

        return [
            [
                'name' => 'pair',
                'key' => hash('sha256', implode("\0", [$scope, 'pair', $account, $networkSignal])),
                'limit' => self::LIMITS['pair'],
            ],
            [
                'name' => 'account',
                'key' => hash('sha256', implode("\0", [$scope, 'account', $account])),
                'limit' => self::LIMITS['account'],
            ],
            [
                'name' => 'network',
                'key' => hash('sha256', implode("\0", [$scope, 'network', $networkSignal])),
                'limit' => self::LIMITS['network'],
            ],
        ];
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

    private function insertConflictClause(): string
    {
        return $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'ON CONFLICT(throttle_key) DO NOTHING'
            : 'ON DUPLICATE KEY UPDATE throttle_key = throttle_key';
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
