<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\EntrySnapshotService;
use App\Model\EventManagers;
use PDO;
use Throwable;

final class EventArchiver
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function archive(int $eventId, ?int $clubId = null): bool
    {
        $this->database->beginTransaction();
        try {
            $scope = $clubId === null ? '' : ' AND ' . EventManagers::clubScopeSql();
            $parameters = $clubId === null ? [$eventId] : [$eventId, $clubId, $clubId];
            $statement = $this->database->prepare(
                'SELECT date, closed FROM events WHERE id = ? AND archived_at IS NULL' . $scope
                . ($this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '')
            );
            $statement->execute($parameters);
            $event = $statement->fetch(PDO::FETCH_ASSOC);
            if ($event === false) {
                $this->database->rollBack();
                return false;
            }
            if (empty($event['closed'])) {
                (new EntrySnapshotService($this->database))->consolidate($eventId, (string) $event['date']);
            }
            $this->database->prepare(
                'UPDATE events SET archived_at = CURRENT_TIMESTAMP, published = 0, closed = 1'
                . ' WHERE id = ? AND archived_at IS NULL' . $scope
            )->execute($parameters);
            $this->database->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }
}
