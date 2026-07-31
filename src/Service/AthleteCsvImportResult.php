<?php

declare(strict_types=1);

namespace App\Service;

final class AthleteCsvImportResult
{
    /**
     * @param list<AthleteImportIssue> $issues
     * @param list<AthleteImportReconciliation> $reconciliations
     */
    public function __construct(
        public readonly int $created,
        public readonly int $updated,
        public readonly int $unchanged = 0,
        public readonly array $issues = [],
        public readonly array $reconciliations = []
    ) {
    }

    public function skipped(): int
    {
        return count($this->issues);
    }
}
