<?php

declare(strict_types=1);

namespace App\Model;

final class Club
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
        public readonly string $contact_first_name,
        public readonly string $contact_last_name,
        public readonly string $contact_phone,
        public readonly ?string $contact_email,
        public readonly string $organization,
        public readonly string $recovery_email,
        public readonly string $password_hash,
        public readonly string $federal_code
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (string) ($data['name'] ?? ''),
            (string) ($data['email'] ?? ''),
            (string) ($data['phone'] ?? ''),
            (string) ($data['contact_first_name'] ?? ''),
            (string) ($data['contact_last_name'] ?? ''),
            (string) ($data['contact_phone'] ?? ''),
            $data['contact_email'] !== '' ? (string) ($data['contact_email']) : null,
            (string) ($data['organization'] ?? ''),
            (string) ($data['recovery_email'] ?? ''),
            (string) ($data['password_hash'] ?? ''),
            (string) ($data['federal_code'] ?? '')
        );
    }

    public static function findByEmail(string $email): ?self
    {
        $stmt = Database::connection()->prepare('SELECT * FROM clubs WHERE email = ?');
        $stmt->execute([self::normalizeEmail($email)]);
        $row = $stmt->fetch();

        return $row ? self::fromArray($row) : null;
    }

    public static function findByName(string $name): ?self
    {
        $stmt = Database::connection()->prepare('SELECT * FROM clubs WHERE name = ?');
        $stmt->execute([$name]);
        $row = $stmt->fetch();

        return $row ? self::fromArray($row) : null;
    }

    public static function findById(int $id): ?self
    {
        $stmt = Database::connection()->prepare('SELECT * FROM clubs WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? self::fromArray($row) : null;
    }

    /**
     * Accepts an array with english keys and maps them to DB columns.
     * @param array<string,mixed> $data
     */
    public static function add(array $data): self
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO clubs (federal_code, name, email, phone, contact_first_name, contact_last_name, contact_phone, contact_email, organization, recovery_email, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $data['federal_code'] ?? '',
            $data['name'] ?? '',
            self::normalizeEmail((string) ($data['email'] ?? '')),
            $data['phone'] ?? '',
            $data['contact_first_name'] ?? '',
            $data['contact_last_name'] ?? '-',
            $data['contact_phone'] ?? '',
            $data['contact_email'] ?? '',
            $data['organization'] ?? 'FIJLKAM',
            $data['recovery_email'] ?? '',
            $data['password_hash'] ?? '',
        ]);

        return self::fromArray(Database::connection()->query('SELECT * FROM clubs WHERE id = LAST_INSERT_ID()')->fetch());
    }

    /** @param array<string, mixed> $data */
    public static function update(int $id, array $data): void
    {
        $parts = [];
        $params = [];
        $allowed = ['name','email','phone','contact_first_name','contact_last_name','contact_phone','contact_email','organization','recovery_email','federal_code','password_hash'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $parts[] = "$field = ?";
                $params[] = $field === 'email'
                    ? self::normalizeEmail((string) $data[$field])
                    : $data[$field];
            }
        }

        if (empty($parts)) {
            return;
        }

        $params[] = $id;
        $sql = 'UPDATE clubs SET ' . implode(', ', $parts) . ' WHERE id = ?';
        Database::connection()->prepare($sql)->execute($params);
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function remove(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM clubs WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** @return list<self> */
    public static function all(): array
    {
        $cached = \App\Core\Cache::get('clubs_all');
        if ($cached !== null) {
            return $cached;
        }

        $stmt = Database::connection()->query('SELECT * FROM clubs ORDER BY name');
        $rows = $stmt->fetchAll();

        $result = array_map(fn(array $row) => self::fromArray($row), $rows ?: []);

        \App\Core\Cache::set('clubs_all', $result, 300);

        return $result;
    }
}
