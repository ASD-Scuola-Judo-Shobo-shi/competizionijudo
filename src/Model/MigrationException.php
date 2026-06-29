<?php

declare(strict_types=1);

namespace App\Model;

use RuntimeException;
use Throwable;

final class MigrationException extends RuntimeException
{
    public function __construct(private readonly string $version, Throwable $previous)
    {
        parent::__construct('Migration failed: ' . $version, 0, $previous);
    }

    public function version(): string
    {
        return $this->version;
    }
}
