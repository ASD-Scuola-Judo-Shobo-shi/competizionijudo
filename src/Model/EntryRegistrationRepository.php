<?php

declare(strict_types=1);

namespace App\Model;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

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
        // Check if club has registration exception for closed events - if so, skip capacity check
        $hasException = EventRegistrationException::exists($eventId, $clubId);

        // Only check capacity if no exception
        if (!$hasException) {
            // Check if event has reached max participants capacity
            $capacityCheck = $this->database->prepare(
                'SELECT max_participants, (SELECT COUNT(athlete_id) FROM entries WHERE event_id = :event_id_for_count) AS current_count
                 FROM events
                 WHERE id = :event_id_capacity'
            );
            $capacityCheck->execute([
                'event_id_for_count' => $eventId,
                'event_id_capacity' => $eventId,
            ]);
            $eventInfo = $capacityCheck->fetch();
            $maxParticipants = $eventInfo ? (int) ($eventInfo['max_participants'] ?? 0) : 0;
            $currentCount = $eventInfo ? (int) ($eventInfo['current_count'] ?? 0) : 0;
            if ($maxParticipants > 0 && $currentCount >= $maxParticipants) {
                return EntryRegistrationResult::CapacityExceeded;
            }
        }

        $ownsTransaction = false;
        if ($hasException && !$this->database->inTransaction()) {
            if (!$this->database->beginTransaction()) {
                throw new RuntimeException('Unable to begin the entry registration transaction.');
            }
            $ownsTransaction = true;
        }

        try {
            $statement = $this->database->prepare(
                'INSERT INTO entries (event_id, club_id, athlete_id)
                 SELECT event_record.id, :entry_club_id, athlete.id
                 FROM athletes AS athlete
                 JOIN events AS event_record ON event_record.id = :event_id
                 WHERE athlete.id = :athlete_id
                   AND athlete.club_id = :athlete_club_id
                   AND event_record.published = 1
                   AND (
                       event_record.closed = 0
                       OR EXISTS (
                           SELECT 1 FROM event_registration_exceptions
                           WHERE event_id = event_record.id AND club_id = :entry_club_id
                       )
                   )
                   AND event_record.date >= :event_date
                   AND (
                       event_record.registration_deadline IS NULL
                       OR event_record.registration_deadline >= :deadline_date
                   )'
            );
            if ($statement === false) {
                throw new RuntimeException('Unable to prepare the entry registration statement.');
            }

            $statement->execute([
                'event_id' => $eventId,
                'entry_club_id' => $clubId,
                'athlete_id' => $athleteId,
                'athlete_club_id' => $clubId,
                'event_date' => $registrationDate,
                'deadline_date' => $registrationDate,
            ]);
            if ($statement->rowCount() !== 1) {
                $this->commitOwnedTransaction($ownsTransaction);

                return EntryRegistrationResult::AthleteRejected;
            }

            if ($hasException) {
                (new EntrySnapshotService($this->database))->consolidateClosedEntry($eventId, $clubId, $athleteId);
            }
            $this->commitOwnedTransaction($ownsTransaction);

            return EntryRegistrationResult::Registered;
        } catch (PDOException $exception) {
            $this->rollBackOwnedTransaction($ownsTransaction);

            if ($this->isDuplicateEntry($exception)) {
                return EntryRegistrationResult::AlreadyRegistered;
            }

            throw $exception;
        } catch (Throwable $exception) {
            $this->rollBackOwnedTransaction($ownsTransaction);

            throw $exception;
        }
    }

    private function isDuplicateEntry(PDOException $exception): bool
    {
        $errorInfo = $exception->errorInfo;

        if (!is_array($errorInfo) || ($errorInfo[0] ?? null) !== '23000') {
            return false;
        }

        $driverCode = (int) ($errorInfo[1] ?? 0);

        return $driverCode === 1062
            || ($driverCode === 19 && str_contains(strtolower((string) ($errorInfo[2] ?? '')), 'unique constraint'));
    }

    private function commitOwnedTransaction(bool $ownsTransaction): void
    {
        if ($ownsTransaction && !$this->database->commit()) {
            throw new RuntimeException('Unable to commit the entry registration transaction.');
        }
    }

    private function rollBackOwnedTransaction(bool $ownsTransaction): void
    {
        if (!$ownsTransaction) {
            return;
        }

        try {
            $this->database->rollBack();
        } catch (PDOException) {
            // The transaction may already be closed after a failed commit.
        }
    }

    public function unregister(int $eventId, int $clubId, int $athleteId, string $registrationDate): EntryRegistrationResult
    {
        // Check if event exists and published (allow unregistration for closed events with exception)
        $eligibilityCheck = $this->database->prepare(
            'SELECT closed FROM events
              WHERE id = ?
                AND published = 1'
        );
        $eligibilityCheck->execute([$eventId]);
        $eventInfo = $eligibilityCheck->fetch();
        if ($eventInfo === false) {
            return EntryRegistrationResult::UnsubscribeFailed;
        }
        // If closed, check if club has exception
        if (!empty($eventInfo['closed']) && !EventRegistrationException::exists($eventId, $clubId)) {
            return EntryRegistrationResult::UnsubscribeFailed;
        }

        // Delete the entry if it belongs to the club
        $statement = $this->database->prepare(
            'DELETE FROM entries
              WHERE event_id = ? AND club_id = ? AND athlete_id = ?'
        );

        $deleted = $statement->execute([$eventId, $clubId, $athleteId]);
        if (!$deleted || $statement->rowCount() === 0) {
            return EntryRegistrationResult::UnsubscribeFailed;
        }

        return EntryRegistrationResult::Unsubscribed;
    }
}
