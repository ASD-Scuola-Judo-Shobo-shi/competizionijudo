<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

final class EntrySnapshotRetentionService
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function purgeBefore(string $cutoff): int
    {
        $statement = $this->database->prepare(
            'DELETE FROM entries
             WHERE event_id IN (SELECT id FROM events WHERE closed = 1)
               AND (
                   (snapshot_at IS NOT NULL AND snapshot_at <= ?)
                   OR (
                       snapshot_at IS NULL
                       AND event_id IN (SELECT id FROM events WHERE closed = 1 AND date <= ?)
                   )
               )'
        );
        $statement->execute([$cutoff, substr($cutoff, 0, 10)]);

        return $statement->rowCount();
    }
}
