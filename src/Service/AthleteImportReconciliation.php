<?php

declare(strict_types=1);

namespace App\Service;

final class AthleteImportReconciliation
{
    /** @param array<string, string> $resolutions */
    public function __construct(
        public readonly int $row,
        public readonly string $identity,
        public readonly int $existingAthleteId,
        public readonly array $resolutions
    ) {
    }
}
