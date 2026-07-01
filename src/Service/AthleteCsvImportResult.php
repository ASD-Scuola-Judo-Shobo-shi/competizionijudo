<?php

declare(strict_types=1);

namespace App\Service;

final class AthleteCsvImportResult
{
    public function __construct(
        public readonly int $created,
        public readonly int $updated
    ) {
    }
}
