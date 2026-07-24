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
            'SELECT en.id, a.last_name, a.first_name, a.gender, a.birth_date,
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
                 snapshot_birth_date = ?, snapshot_weight_kg = ?, snapshot_belt = ?,
                 snapshot_membership_number = ?, snapshot_program = ?,
                 snapshot_weight_category = ?, snapshot_at = CURRENT_TIMESTAMP
             WHERE id = ? AND event_id = ?'
        );

        foreach ($entries->fetchAll() as $entry) {
            $category = JudoCategory::calculate(
                (string) $entry['birth_date'],
                (string) $entry['gender'],
                (float) $entry['weight_kg'],
                Athlete::eventYearFromDate($eventDate)
            );
            $update->execute([
                $entry['last_name'],
                $entry['first_name'],
                $entry['gender'],
                $entry['birth_date'],
                $entry['weight_kg'],
                $entry['belt'],
                $entry['membership_number'],
                $category['type'],
                $category['weight_category'],
                $entry['id'],
                $eventId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Unable to consolidate an event entry snapshot.');
            }
        }
    }

    /**
     * Snapshot one entry that was registered after an event was closed.
     *
     * Returns false when the event is open, so registrations allowed by an
     * exception do not acquire a snapshot before the event is closed.
     */
    public function consolidateClosedEntry(int $eventId, int $clubId, int $athleteId): bool
    {
        $entryStatement = $this->database->prepare(
            'SELECT en.id, a.last_name, a.first_name, a.gender, a.birth_date,
                    a.weight_kg, a.belt, a.membership_number, e.date AS event_date
             FROM entries en
             JOIN athletes a ON a.id = en.athlete_id
             JOIN events e ON e.id = en.event_id
             WHERE en.event_id = ? AND en.club_id = ? AND en.athlete_id = ? AND e.closed = 1'
        );
        $entryStatement->execute([$eventId, $clubId, $athleteId]);
        $entry = $entryStatement->fetch();
        if ($entry === false) {
            return false;
        }

        $category = JudoCategory::calculate(
            (string) $entry['birth_date'],
            (string) $entry['gender'],
            (float) $entry['weight_kg'],
            Athlete::eventYearFromDate((string) $entry['event_date'])
        );
        $update = $this->database->prepare(
            'UPDATE entries
             SET snapshot_last_name = ?, snapshot_first_name = ?, snapshot_gender = ?,
                 snapshot_birth_date = ?, snapshot_weight_kg = ?, snapshot_belt = ?,
                 snapshot_membership_number = ?, snapshot_program = ?,
                 snapshot_weight_category = ?, snapshot_at = CURRENT_TIMESTAMP
             WHERE id = ? AND event_id = ?'
        );
        $update->execute([
            $entry['last_name'],
            $entry['first_name'],
            $entry['gender'],
            $entry['birth_date'],
            $entry['weight_kg'],
            $entry['belt'],
            $entry['membership_number'],
            $category['type'],
            $category['weight_category'],
            $entry['id'],
            $eventId,
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Unable to consolidate an event entry snapshot.');
        }

        return true;
    }
}
