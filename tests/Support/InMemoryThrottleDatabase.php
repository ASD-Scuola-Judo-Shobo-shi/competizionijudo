<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use PDOStatement;
use RuntimeException;

/** @internal Test fixture that exercises the throttle's SQL contract without a live database. */
final class InMemoryThrottleDatabase extends PDO
{
    /**
     * @var array<string, array{
     *     attempt_count: int,
     *     window_started_at: string,
     *     blocked_until: null|string,
     *     updated_at: string
     * }>
     */
    public array $records = [];

    /** @var list<list<mixed>> */
    public array $executedParameters = [];

    /** @var list<string> */
    private array $executedSql = [];

    public int $cleanupDeleted = 0;
    private bool $transactionActive = false;

    public function __construct()
    {
    }

    /** @param array<array-key, mixed> $options */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new InMemoryThrottleStatement($this, $query);
    }

    public function beginTransaction(): bool
    {
        $this->transactionActive = true;

        return true;
    }

    public function commit(): bool
    {
        $this->transactionActive = false;

        return true;
    }

    public function rollBack(): bool
    {
        $this->transactionActive = false;

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transactionActive;
    }

    public function seedStaleRecords(int $count): void
    {
        for ($index = 0; $index < $count; $index++) {
            $this->records[hash('sha256', 'stale-' . $index)] = [
                'attempt_count' => 1,
                'window_started_at' => '2026-06-01 00:00:00',
                'blocked_until' => null,
                'updated_at' => '2026-06-01 00:00:00',
            ];
        }
    }

    public function sawSql(string $sql): bool
    {
        return in_array($sql, $this->executedSql, true);
    }

    /**
     * @param list<mixed> $parameters
     * @return array{row: mixed}
     */
    public function run(string $sql, array $parameters): array
    {
        $this->executedSql[] = $sql;
        $this->executedParameters[] = $parameters;

        if (str_starts_with($sql, 'INSERT INTO authentication_throttles')) {
            $key = (string) $parameters[0];
            if (!isset($this->records[$key])) {
                $this->records[$key] = [
                    'attempt_count' => 0,
                    'window_started_at' => (string) $parameters[1],
                    'blocked_until' => null,
                    'updated_at' => (string) $parameters[2],
                ];
            }

            return ['row' => false];
        }

        if (str_starts_with($sql, 'SELECT blocked_until')) {
            $record = $this->records[(string) $parameters[0]] ?? null;

            return ['row' => $record === null ? false : ['blocked_until' => $record['blocked_until']]];
        }

        if (str_starts_with($sql, 'SELECT attempt_count')) {
            return ['row' => $this->records[(string) $parameters[0]] ?? false];
        }

        if (str_starts_with($sql, 'UPDATE authentication_throttles')) {
            $key = (string) $parameters[4];
            $this->records[$key] = [
                'attempt_count' => (int) $parameters[0],
                'window_started_at' => (string) $parameters[1],
                'blocked_until' => is_string($parameters[2]) ? $parameters[2] : null,
                'updated_at' => (string) $parameters[3],
            ];

            return ['row' => false];
        }

        if (str_starts_with($sql, 'DELETE FROM authentication_throttles WHERE updated_at')) {
            $deleted = 0;
            foreach ($this->records as $key => $record) {
                if ($record['updated_at'] < (string) $parameters[0] && $deleted < 100) {
                    unset($this->records[$key]);
                    $deleted++;
                }
            }
            $this->cleanupDeleted += $deleted;

            return ['row' => false];
        }

        if (str_starts_with($sql, 'DELETE FROM authentication_throttles WHERE throttle_key')) {
            unset($this->records[(string) $parameters[0]]);

            return ['row' => false];
        }

        throw new RuntimeException('Unexpected SQL in throttle fixture: ' . $sql);
    }
}
