<?php

declare(strict_types=1);

namespace App\Model;

use PDO;
use RuntimeException;

final class EntrySnapshotService
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function consolidate(int $eventId, string $eventDate): void
    {
        $entries = $this->database->prepare(
            'SELECT en.id, a.last_name, a.first_name, a.gender, a.date_of_birth,
                    a.weight_kg, a.belt, a.membership_number
             FROM entries en
             JOIN athletes a ON a.id = en.athlete_id
             WHERE en.event_id = ?
             ORDER BY en.id'
        );
        $entries->execute([$eventId]);
        $update = $this->database->prepare(
            'UPDATE entries
             SET snapshot_last_name = ?, snapshot_first_name = ?, snapshot_gender = ?,
                 snapshot_date_of_birth = ?, snapshot_weight_kg = ?, snapshot_belt = ?,
                 snapshot_membership_number = ?, snapshot_program = ?,
                 snapshot_weight_category = ?, snapshot_at = CURRENT_TIMESTAMP
             WHERE id = ? AND event_id = ?'
        );

        foreach ($entries->fetchAll() as $entry) {
            $category = JudoCategory::calculate(
                (string) $entry['date_of_birth'],
                (string) $entry['gender'],
                (float) $entry['weight_kg'],
                Athlete::eventYearFromDate($eventDate)
            );
            $update->execute([
                $entry['last_name'],
                $entry['first_name'],
                $entry['gender'],
                $entry['date_of_birth'],
                $entry['weight_kg'],
                $entry['belt'],
                $entry['membership_number'],
                $category['program'],
                $category['weight_category'],
                $entry['id'],
                $eventId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Unable to consolidate an event entry snapshot.');
            }
        }
    }
}
