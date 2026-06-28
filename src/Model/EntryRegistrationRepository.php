<?php

declare(strict_types=1);

namespace App\Model;

use PDO;
use PDOException;
use RuntimeException;

final class EntryRegistrationRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function register(
        int $eventId,
        int $clubId,
        int $athleteId,
        string $registrationDate
    ): EntryRegistrationResult {
        $statement = $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id)
             SELECT event_record.id, :entry_club_id, athlete.id
             FROM athletes AS athlete
             JOIN events AS event_record ON event_record.id = :event_id
             WHERE athlete.id = :athlete_id
               AND athlete.club_id = :athlete_club_id
               AND event_record.published = 1
               AND event_record.closed = 0
               AND event_record.date >= :event_date
               AND (
                   event_record.registration_deadline IS NULL
                   OR event_record.registration_deadline >= :deadline_date
               )'
        );
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare the entry registration statement.');
        }

        try {
            $statement->execute([
                'event_id' => $eventId,
                'entry_club_id' => $clubId,
                'athlete_id' => $athleteId,
                'athlete_club_id' => $clubId,
                'event_date' => $registrationDate,
                'deadline_date' => $registrationDate,
            ]);
        } catch (PDOException $exception) {
            if ($this->isDuplicateEntry($exception)) {
                return EntryRegistrationResult::AlreadyRegistered;
            }

            throw $exception;
        }

        return $statement->rowCount() === 1
            ? EntryRegistrationResult::Registered
            : EntryRegistrationResult::AthleteRejected;
    }

    private function isDuplicateEntry(PDOException $exception): bool
    {
        $errorInfo = $exception->errorInfo;

        return is_array($errorInfo)
            && ($errorInfo[0] ?? null) === '23000'
            && (int) ($errorInfo[1] ?? 0) === 1062;
    }
}
