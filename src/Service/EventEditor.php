<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Logger;
use App\Model\Database;
use App\Model\EntrySnapshotService;
use App\Model\Event;
use App\Model\EventManagers;
use App\Model\EventRegistrationException;
use App\Model\EventRegistrationOption;

final class EventEditor
{
    public function __construct(private readonly EventUploadStorage $eventUploadStorage, private readonly Logger $logger)
    {
    }

    /**
     * @param array{
     *     name: string,
     *     date: string,
     *     location: string,
     *     organizer: string,
     *     registration_deadline: string,
     *     type: string,
     *     description: string,
     *     notes: string,
     *     max_participants: string,
     *     published: int,
     *     closed: int,
     *     registration_options: list<array{
     *         id:int|null,
     *         name:string,
     *         fee_amount:string,
     *         fee_cents:int|null,
     *         is_default:bool
     *     }>,
     *     sepa_account_holder:string,
     *     sepa_iban:string,
     *     sepa_bic:string
     * } $data
     * @param array<string, array<string, mixed>> $uploads
     * @param list<int> $exceptionClubIds
     * @param list<int>|null $managerClubIds
     */
    public function save(
        ?Event $event,
        array $data,
        array $uploads,
        array $exceptionClubIds,
        ?int $clubId,
        string $correlationId,
        ?array $managerClubIds = null
    ): int {
        $db = Database::connection();
        $eventId = $event->id ?? 0;
        $storedUploads = [];
        $persisted = false;
        try {
            $locandina = $event->poster_file ?? null;
            $publicPoster = $event->public_poster_file ?? null;
            $informativa = $event->info_file ?? null;

            if (isset($uploads['poster_file'])) {
                $locandina = $this->eventUploadStorage->store($uploads['poster_file'], 'poster_');
                $storedUploads[] = $locandina;
            }
            if (isset($uploads['public_poster_file'])) {
                $publicPoster = $this->eventUploadStorage->store($uploads['public_poster_file'], 'poster_public_');
                $storedUploads[] = $publicPoster;
            }
            if (isset($uploads['info_file'])) {
                $informativa = $this->eventUploadStorage->store($uploads['info_file'], 'info_');
                $storedUploads[] = $informativa;
            }

            $db->beginTransaction();
            if ($event !== null) {
                $scope = $clubId === null ? '' : ' AND ' . EventManagers::clubScopeSql();
                $lock = $db->prepare(
                    'SELECT * FROM events WHERE id = ? AND archived_at IS NULL' . $scope
                    . ($db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '')
                );
                $lock->execute($clubId === null ? [$eventId] : [$eventId, $clubId, $clubId]);
                $current = $lock->fetch(\PDO::FETCH_ASSOC);
                if ($current === false) {
                    throw new \RuntimeException('Event is no longer editable.');
                }
            }
            if ($event) {
                $sql = "UPDATE events SET name=?, date=?, location=?, organizer=?, registration_deadline=?, type=?, description=?, notes=?, max_participants=?, poster_file=?, public_poster_file=?, info_file=?, published=?, closed=?, sepa_account_holder=?, sepa_iban=?, sepa_bic=? WHERE id=? AND archived_at IS NULL";
                $params = [
                    $data['name'],
                    $data['date'],
                    $data['location'],
                    $data['organizer'],
                    $data['registration_deadline'] ?: null,
                    $data['type'],
                    $data['description'],
                    $data['notes'],
                    $data['max_participants'] !== '' ? (int) $data['max_participants'] : null,
                    $locandina,
                    $publicPoster,
                    $informativa,
                    $data['published'],
                    $data['closed'],
                    $data['sepa_account_holder'] !== '' ? $data['sepa_account_holder'] : null,
                    $data['sepa_iban'] !== '' ? $data['sepa_iban'] : null,
                    $data['sepa_bic'] !== '' ? $data['sepa_bic'] : null,
                    $eventId,
                ];
                if ($clubId !== null) {
                    $sql .= ' AND ' . EventManagers::clubScopeSql();
                    $params[] = $clubId;
                    $params[] = $clubId;
                }
                $db->prepare($sql)->execute($params);
                if (empty($current['closed']) && $data['closed'] === 1) {
                    (new EntrySnapshotService($db))->consolidate($eventId, $data['date']);
                }
            } else {
                $ownerColumn = $clubId === null ? '' : ', owner_club_id';
                $ownerPlaceholder = $clubId === null ? '' : ', ?';
                $insertSql = 'INSERT INTO events (name, date, location, organizer, registration_deadline, type, '
                    . 'description, notes, max_participants, poster_file, public_poster_file, info_file, published, closed, '
                    . 'sepa_account_holder, sepa_iban, sepa_bic' . $ownerColumn . ') '
                    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?' . $ownerPlaceholder . ')';
                $insertParameters = [
                    $data['name'],
                    $data['date'],
                    $data['location'],
                    $data['organizer'],
                    $data['registration_deadline'] ?: null,
                    $data['type'],
                    $data['description'],
                    $data['notes'],
                    $data['max_participants'] !== '' ? (int) $data['max_participants'] : null,
                    $locandina,
                    $publicPoster,
                    $informativa,
                    $data['published'],
                    $data['closed'],
                    $data['sepa_account_holder'] !== '' ? $data['sepa_account_holder'] : null,
                    $data['sepa_iban'] !== '' ? $data['sepa_iban'] : null,
                    $data['sepa_bic'] !== '' ? $data['sepa_bic'] : null,
                ];
                if ($clubId !== null) {
                    $insertParameters[] = $clubId;
                }
                $db->prepare($insertSql)->execute($insertParameters);
                $eventId = (int) $db->lastInsertId();
            }

            /** @var list<array{id:int|null, name:string, fee_cents:int, is_default:bool}> $options */
            $options = array_map(
                static fn(array $option): array => [
                    'id' => $option['id'],
                    'name' => $option['name'],
                    'fee_cents' => (int) $option['fee_cents'],
                    'is_default' => $option['is_default'],
                ],
                $data['registration_options']
            );
            EventRegistrationOption::synchronize($db, $eventId, $options);
            if ($clubId === null) {
                EventRegistrationException::setForEvent($eventId, $exceptionClubIds);
            }
            if ($managerClubIds !== null) {
                $creatorClubId = isset($current) ? (int) ($current['owner_club_id'] ?? 0) : $clubId;
                if ($clubId !== null && $creatorClubId !== $clubId) {
                    throw new \RuntimeException('Only the creator club can change managing clubs.');
                }
                EventManagers::synchronize($db, $eventId, $managerClubIds);
            }
            $db->commit();
            $persisted = true;

            if ($event !== null) {
                $replacedUploads = [];
                if (isset($uploads['poster_file'])) {
                    $replacedUploads[] = $event->poster_file;
                }
                if (isset($uploads['public_poster_file'])) {
                    $replacedUploads[] = $event->public_poster_file;
                }
                if (isset($uploads['info_file'])) {
                    $replacedUploads[] = $event->info_file;
                }
                $this->eventUploadStorage->purgeMany($replacedUploads);
            }

            return $eventId;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if (!$persisted) {
                try {
                    $this->eventUploadStorage->purgeMany($storedUploads);
                } catch (\Throwable $cleanupException) {
                    $this->logger->error(
                        'admin.new_event_upload_cleanup_failed',
                        $cleanupException,
                        $correlationId
                    );
                }
            }
            throw $exception;
        }
    }
}
