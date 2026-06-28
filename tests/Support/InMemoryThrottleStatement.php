<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use PDOStatement;

/** @internal */
final class InMemoryThrottleStatement extends PDOStatement
{
    private mixed $row = false;
    private bool $fetched = false;

    public function __construct(
        private readonly InMemoryThrottleDatabase $database,
        private readonly string $sql
    ) {
    }

    /** @param null|list<mixed> $params */
    public function execute(?array $params = null): bool
    {
        $result = $this->database->run($this->sql, $params ?? []);
        $this->row = $result['row'];
        $this->fetched = false;

        return true;
    }

    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        if ($this->fetched) {
            return false;
        }

        $this->fetched = true;

        return $this->row;
    }
}
