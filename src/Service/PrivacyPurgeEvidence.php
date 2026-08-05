<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class PrivacyPurgeEvidence
{
    public function __construct(private readonly string $path)
    {
    }

    /**
     * Appends a timestamped, append-only evidence line for a privacy purge run.
     *
     * @param array<string, int|string|null> $facts
     */
    public function record(string $status, array $facts = [], ?string $correlationId = null): void
    {
        $line = json_encode([
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'status' => $status,
            'correlation_id' => $correlationId,
            'facts' => $facts,
        ], JSON_THROW_ON_ERROR);
        if (@file_put_contents($this->path, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Unable to append the privacy purge evidence record.');
        }
    }
}
