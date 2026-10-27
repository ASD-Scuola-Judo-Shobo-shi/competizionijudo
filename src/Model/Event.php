<?php

declare(strict_types=1);

namespace App\Model;

final class Event
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $date,
        public readonly string $location,
        public readonly string $organizer,
        public readonly string $registration_deadline,
        public readonly string $type,
        public readonly ?string $description,
        public readonly ?string $notes,
        public readonly ?string $poster_file,
        public readonly ?string $info_file,
        public readonly bool $published,
        public readonly bool $closed,
        public readonly ?int $max_participants,
        public readonly ?string $sepa_account_holder = null,
        public readonly ?string $sepa_iban = null,
        public readonly ?string $sepa_bic = null,
        public readonly ?int $owner_club_id = null,
        public readonly ?string $archived_at = null,
    ) {
    }

    /** @return list<EventRegistrationOption> */
    public function registrationOptions(): array
    {
        return EventRegistrationOption::activeForEvent($this->id);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (string) ($data['name'] ?? ''),
            (string) ($data['date'] ?? ''),
            (string) ($data['location'] ?? ''),
            (string) ($data['organizer'] ?? ''),
            (string) ($data['registration_deadline'] ?? ''),
            (string) ($data['type'] ?? ''),
            $data['description'] !== '' ? (string) $data['description'] : null,
            $data['notes'] !== '' ? (string) $data['notes'] : null,
            $data['poster_file'] !== '' ? (string) $data['poster_file'] : null,
            $data['info_file'] !== '' ? (string) $data['info_file'] : null,
            !empty($data['published']),
            !empty($data['closed']),
            isset($data['max_participants']) && (int) $data['max_participants'] > 0
                ? (int) $data['max_participants']
                : null,
            !empty($data['sepa_account_holder']) ? (string) $data['sepa_account_holder'] : null,
            !empty($data['sepa_iban']) ? (string) $data['sepa_iban'] : null,
            !empty($data['sepa_bic']) ? (string) $data['sepa_bic'] : null,
            isset($data['owner_club_id']) ? (int) $data['owner_club_id'] : null,
            isset($data['archived_at']) ? (string) $data['archived_at'] : null,
        );
    }

    /** @return list<self> */
    public static function upcomingPublished(string $onDate, int $limit): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM events
             WHERE published = 1 AND archived_at IS NULL AND closed = 0 AND date >= ?
             ORDER BY date ASC, id ASC
             LIMIT ?'
        );
        $stmt->bindValue(1, $onDate);
        $stmt->bindValue(2, max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn(array $r) => self::fromArray($r), $rows ?: []);
    }

    /**
     * Retrieves upcoming published events including closed ones, ordered by date ascending.
     * @return list<self>
     */
    public static function upcomingPublishedIncludingClosed(string $onDate, int $limit): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM events
             WHERE published = 1 AND archived_at IS NULL AND date >= ?
             ORDER BY date ASC, id ASC
             LIMIT ?'
        );
        $stmt->bindValue(1, $onDate);
        $stmt->bindValue(2, max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn(array $r) => self::fromArray($r), $rows ?: []);
    }

    /**
     * Retrieves the next upcoming published events including closed ones, excluding a specific ID.
     * @return list<self>
     */
    public static function nextUpcomingPublishedIncludingClosed(
        int $excludeId,
        string $onDate,
        int $limit
    ): array {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM events
             WHERE published = 1 AND archived_at IS NULL AND date >= ? AND id != ?
             ORDER BY date ASC, id ASC
             LIMIT ?'
        );
        $stmt->bindValue(1, $onDate);
        $stmt->bindValue(2, $excludeId, \PDO::PARAM_INT);
        $stmt->bindValue(3, max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn(array $r) => self::fromArray($r), $rows ?: []);
    }

    public static function findById(int $id): ?self
    {
        $stmt = Database::connection()->prepare('SELECT * FROM events WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? self::fromArray($row) : null;
    }

    public static function findPublishedById(int $id): ?self
    {
        $stmt = Database::connection()->prepare('SELECT * FROM events WHERE id = ? AND published = 1 AND archived_at IS NULL');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? self::fromArray($row) : null;
    }

    public static function findPublishedByIdIncludingClosed(int $id): ?self
    {
        $stmt = Database::connection()->prepare('SELECT * FROM events WHERE id = ? AND published = 1 AND archived_at IS NULL');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? self::fromArray($row) : null;
    }

    public static function findPublishedByIdOrClosedWithEntries(int $id, int $clubId): ?self
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.* FROM events e
             WHERE e.id = ? AND e.published = 1 AND e.archived_at IS NULL
               AND (e.closed = 0 
                    OR EXISTS (SELECT 1 FROM entries WHERE event_id = e.id AND club_id = ?)
                    OR EXISTS (SELECT 1 FROM event_registration_exceptions WHERE event_id = e.id AND club_id = ?))'
        );
        $stmt->execute([$id, $clubId, $clubId]);
        $row = $stmt->fetch();

        return $row ? self::fromArray($row) : null;
    }

    public static function findRegistrationEligibleById(int $id, string $onDate): ?self
    {
        return self::findRegistrationEligibleByIdForClub($id, $onDate, null);
    }

    public static function findRegistrationEligibleByIdForClub(int $id, string $onDate, ?int $clubId): ?self
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM events
             WHERE id = ?
               AND published = 1 AND archived_at IS NULL
               AND (closed = 0 OR EXISTS (
                     SELECT 1 FROM event_registration_exceptions WHERE event_id = ? AND club_id = ?
                 ))
               AND date >= ?
               AND (registration_deadline IS NULL OR registration_deadline >= ?)'
        );
        $stmt->bindValue(1, $id, \PDO::PARAM_INT);
        $stmt->bindValue(2, $id, \PDO::PARAM_INT);
        $stmt->bindValue(3, $clubId);
        $stmt->bindValue(4, $onDate);
        $stmt->bindValue(5, $onDate);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row ? self::fromArray($row) : null;
    }

    /** @return list<self> */
    public static function allPublishedEligible(string $onDate, int $limit, ?int $clubId = null): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM events
             WHERE published = 1 AND archived_at IS NULL
               AND (closed = 0 OR EXISTS (
                     SELECT 1 FROM event_registration_exceptions WHERE event_id = events.id AND club_id = ?
                 ))
               AND date >= ?
               AND (registration_deadline IS NULL OR registration_deadline >= ?)'
        );
        $stmt->bindValue(1, $clubId);
        $stmt->bindValue(2, $onDate);
        $stmt->bindValue(3, $onDate);
        $stmt->bindValue(4, max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn(array $r) => self::fromArray($r), $rows ?: []);
    }

    /** @return list<self> */
    public static function nextUpcomingPublishedEligible(
        int $excludeId,
        string $onDate,
        int $limit,
        ?int $clubId = null
    ): array {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM events
             WHERE published = 1 AND archived_at IS NULL
               AND (closed = 0 OR EXISTS (
                     SELECT 1 FROM event_registration_exceptions WHERE event_id = events.id AND club_id = ?
                 ))
               AND date >= ?
               AND id != ?
           ORDER BY date ASC, id ASC
           LIMIT ?'
        );
        $stmt->bindValue(1, $clubId);
        $stmt->bindValue(2, $onDate);
        $stmt->bindValue(3, $excludeId, \PDO::PARAM_INT);
        $stmt->bindValue(4, max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn(array $r) => self::fromArray($r), $rows ?: []);
    }

    public static function findManagedById(int $id, int $clubId): ?self
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM events WHERE id = ? AND ' . EventManagers::clubScopeSql()
        );
        $statement->execute([$id, $clubId, $clubId]);
        $row = $statement->fetch();
        return $row ? self::fromArray($row) : null;
    }

    public static function countManaged(int $clubId, bool $archived = false): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM events WHERE ' . EventManagers::clubScopeSql() . ' AND archived_at IS '
            . ($archived ? 'NOT NULL' : 'NULL')
        );
        $statement->execute([$clubId, $clubId]);
        return (int) $statement->fetchColumn();
    }

    /** @return list<self> */
    public static function pageManaged(int $clubId, int $limit, int $offset, bool $archived = false): array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM events WHERE ' . EventManagers::clubScopeSql() . ' AND archived_at IS ' . ($archived ? 'NOT NULL' : 'NULL')
            . ' ORDER BY date DESC, id DESC LIMIT ? OFFSET ?'
        );
        $statement->bindValue(1, $clubId, \PDO::PARAM_INT);
        $statement->bindValue(2, $clubId, \PDO::PARAM_INT);
        $statement->bindValue(3, max(1, $limit), \PDO::PARAM_INT);
        $statement->bindValue(4, max(0, $offset), \PDO::PARAM_INT);
        $statement->execute();
        return array_map(fn(array $row) => self::fromArray($row), $statement->fetchAll() ?: []);
    }

    /** @return list<string> */
    public static function locations(?int $clubId = null): array
    {
        if ($clubId === null) {
            $statement = Database::connection()->query(
                'SELECT DISTINCT location FROM events WHERE location != "" ORDER BY location ASC'
            );
        } else {
            $statement = Database::connection()->prepare(
                'SELECT DISTINCT location FROM events WHERE location != ? AND ' . EventManagers::clubScopeSql()
                . ' ORDER BY location ASC'
            );
            $statement->execute(['', $clubId, $clubId]);
        }
        return array_map(static fn(array $row): string => (string) $row['location'], $statement->fetchAll());
    }

    public static function remove(int $id): void
    {
        $statement = Database::connection()->prepare('DELETE FROM events WHERE id = ?');
        $statement->execute([$id]);
    }

    public static function count(bool $archived = false): int
    {
        return (int) Database::connection()->query(
            'SELECT COUNT(*) FROM events WHERE archived_at IS ' . ($archived ? 'NOT NULL' : 'NULL')
        )->fetchColumn();
    }

    /** @return list<self> */
    public static function adminPage(
        int $limit,
        int $offset,
        string $sort = 'date',
        string $direction = 'desc',
        bool $archived = false
    ): array {
        $sortExpression = match ($sort) {
            'name' => 'e.name',
            'location' => 'e.location',
            'type' => 'e.type',
            'visibility' => 'e.published',
            'registration_status' => 'e.closed',
            'clubs' => '(SELECT COUNT(DISTINCT en.club_id) FROM entries en WHERE en.event_id = e.id)',
            'athletes' => '(SELECT COUNT(*) FROM entries en WHERE en.event_id = e.id)',
            'max_participants' => 'e.max_participants',
            default => 'e.date',
        };
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $statement = Database::connection()->prepare(
            'SELECT e.* FROM events e WHERE e.archived_at IS ' . ($archived ? 'NOT NULL' : 'NULL')
            . ' ORDER BY ' . $sortExpression . ' ' . $direction . ', e.id ASC'
            . ' LIMIT ? OFFSET ?'
        );
        $statement->bindValue(1, max(1, $limit), \PDO::PARAM_INT);
        $statement->bindValue(2, max(0, $offset), \PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn(array $row) => self::fromArray($row), $statement->fetchAll() ?: []);
    }

    /** @return list<self> */
    public static function allPublishedIncludingClosed(string $onDate, int $limit): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM events
             WHERE published = 1 AND archived_at IS NULL
             ORDER BY date DESC, id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn(array $r) => self::fromArray($r), $rows ?: []);
    }

    /** @return list<self> */
    public static function nextUpcomingPublished(
        int $excludeId,
        string $onDate,
        int $limit
    ): array {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM events
             WHERE published = 1 AND archived_at IS NULL AND closed = 0 AND date >= ? AND id != ?
             ORDER BY date ASC, id ASC
             LIMIT ?'
        );
        $stmt->bindValue(1, $onDate);
        $stmt->bindValue(2, $excludeId, \PDO::PARAM_INT);
        $stmt->bindValue(3, max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn(array $r) => self::fromArray($r), $rows ?: []);
    }
}
