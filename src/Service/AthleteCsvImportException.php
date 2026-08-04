<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class AthleteCsvImportException extends RuntimeException
{
    /**
     * @param list<string> $validationKeys
     * @param array<string, string> $replacements
     */
    public function __construct(
        public readonly string $translationKey,
        public readonly ?int $row = null,
        public readonly array $validationKeys = [],
        public readonly array $replacements = []
    ) {
        parent::__construct($translationKey);
    }
}
