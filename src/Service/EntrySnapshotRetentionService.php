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
             WHERE snapshot_at IS NOT NULL
               AND snapshot_at < ?
               AND event_id IN (SELECT id FROM events WHERE closed = 1)'
        );
        $statement->execute([$cutoff]);

        return $statement->rowCount();
    }
}
