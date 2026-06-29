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
    ) {
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
        );
    }

    /** @return list<self> */
    public static function allPublished(?int $limit = null): array
    {
        $sql = 'SELECT * FROM events WHERE published=1 AND closed=0 ORDER BY date';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        $stmt = Database::connection()->query($sql);
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
        $stmt = Database::connection()->prepare('SELECT * FROM events WHERE id = ? AND published = 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? self::fromArray($row) : null;
    }

    public static function findRegistrationEligibleById(int $id, string $onDate): ?self
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM events
             WHERE id = ?
               AND published = 1
               AND closed = 0
               AND date >= ?
               AND (registration_deadline IS NULL OR registration_deadline >= ?)'
        );
        $stmt->execute([$id, $onDate, $onDate]);
        $row = $stmt->fetch();

        return $row ? self::fromArray($row) : null;
    }

    public static function remove(int $id): void
    {
        $statement = Database::connection()->prepare('DELETE FROM events WHERE id = ?');
        $statement->execute([$id]);
    }

    /** @return list<self> */
    public static function nextPublished(int $excludeId, ?int $limit = null): array
    {
        $sql = 'SELECT * FROM events WHERE published=1 AND closed=0 AND id != ? ORDER BY date ASC';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$excludeId]);
        $rows = $stmt->fetchAll();

        return array_map(fn(array $r) => self::fromArray($r), $rows ?: []);
    }
}
