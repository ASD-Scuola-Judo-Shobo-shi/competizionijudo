<?php

declare(strict_types=1);

namespace App\Service;

final class AthleteImportIssue
{
    /**
     * @param list<string> $validationKeys
     * @param list<string> $fields
     */
    public function __construct(
        public readonly int $row,
        public readonly string $identity,
        public readonly string $translationKey,
        public readonly array $validationKeys = [],
        public readonly array $fields = [],
        public readonly ?int $existingAthleteId = null
    ) {
    }
}
