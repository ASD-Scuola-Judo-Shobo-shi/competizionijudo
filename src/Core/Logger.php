<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

interface Logger
{
    /** @param array<string, mixed> $context */
    public function error(
        string $event,
        Throwable $exception,
        string $correlationId,
        array $context = []
    ): void;
}
