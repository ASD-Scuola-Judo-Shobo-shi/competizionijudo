<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use PDOStatement;

final class CriticalWorkflowPdo extends PDO
{
    /** @param array<int, mixed> $options */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
