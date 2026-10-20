<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

final class AthleteRevisionRetentionService
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function purgeBefore(string $cutoff): int
    {
        // Keep the current profile revision and revisions referenced by retained entry reviews.
        $statement = $this->database->prepare(
            'DELETE FROM athlete_revisions WHERE recorded_at <= ?
             AND id NOT IN (SELECT latest_id FROM (
                 SELECT MAX(id) AS latest_id FROM athlete_revisions GROUP BY athlete_id
             ) current_revisions)
             AND NOT EXISTS (SELECT 1 FROM entry_data_reviews r WHERE r.revision_id = athlete_revisions.id)'
        );
        $statement->execute([$cutoff]);

        return $statement->rowCount();
    }
}
