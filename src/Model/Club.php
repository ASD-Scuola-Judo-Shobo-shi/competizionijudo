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
        public readonly ?string $address_line,
        public readonly ?string $postal_code,
        public readonly string $city,
        public readonly string $province,
        public readonly string $contact_first_name,
        public readonly string $contact_last_name,
        public readonly ?string $affiliation,
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
            ($data['address_line'] ?? '') !== '' ? (string) $data['address_line'] : null,
            ($data['postal_code'] ?? '') !== '' ? (string) $data['postal_code'] : null,
            (string) ($data['city'] ?? ''),
            (string) ($data['province'] ?? ''),
            (string) ($data['contact_first_name'] ?? ''),
            (string) ($data['contact_last_name'] ?? ''),
            self::nullableString($data['affiliation'] ?? null),
            (string) ($data['password_hash'] ?? ''),
            (string) ($data['federal_code'] ?? '')
        );
    }

    public static function findByEmail(string $email): ?self
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, email, password_hash '
            . 'FROM clubs WHERE normalized_email = ?'
        );
        $stmt->execute([self::normalizeEmail($email)]);
        $row = $stmt->fetch();

        return $row ? self::fromArray($row) : null;
    }

    public static function findForLayoutById(int $id): ?self
    {
        $stmt = Database::connection()->prepare('SELECT id, email FROM clubs WHERE id = ?');
        $stmt->execute([$id]);
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
            'INSERT INTO clubs (federal_code, name, email, phone, address_line, postal_code, city, province, contact_first_name, contact_last_name, affiliation, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $data['federal_code'] ?? '',
            $data['name'] ?? '',
            self::normalizeEmail((string) ($data['email'] ?? '')),
            $data['phone'] ?? '',
            $data['address_line'] ?? null,
            $data['postal_code'] ?? null,
            $data['city'] ?? '',
            $data['province'] ?? '',
            $data['contact_first_name'] ?? '',
            $data['contact_last_name'] ?? '-',
            $data['affiliation'] ?? null,
            $data['password_hash'] ?? '',
        ]);

        return self::fromArray(Database::connection()->query('SELECT * FROM clubs WHERE id = LAST_INSERT_ID()')->fetch());
    }

    /** @param array<string, mixed> $data */
    public static function update(int $id, array $data): void
    {
        $parts = [];
        $params = [];
        $allowed = ['name','email','phone','address_line','postal_code','city','province','contact_first_name','contact_last_name','affiliation','federal_code','password_hash'];

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

    /** @return list<string> */
    public function affiliations(): array
    {
        return Affiliation::decode($this->affiliation);
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    public static function remove(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM clubs WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function count(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM clubs')->fetchColumn();
    }

    /** @return list<self> */
    public static function page(
        int $limit,
        int $offset,
        string $sort = 'name',
        string $direction = 'asc'
    ): array {
        $sortExpression = match ($sort) {
            'federal_code' => 'federal_code',
            default => 'name',
        };
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $stmt = Database::connection()->prepare(
            'SELECT id, federal_code, name '
            . 'FROM clubs ORDER BY ' . $sortExpression . ' ' . $direction . ', id ASC LIMIT ? OFFSET ?'
        );
        $stmt->bindValue(1, max(1, $limit), \PDO::PARAM_INT);
        $stmt->bindValue(2, max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn(array $row) => self::fromArray($row), $rows ?: []);
    }

    /** @return list<self> */
    public static function adminPage(
        int $limit,
        int $offset,
        string $sort = 'name',
        string $direction = 'asc'
    ): array {
        $sortExpression = match ($sort) {
            'federal_code' => 'c.federal_code',
            'email' => 'c.email',
            'phone' => 'c.phone',
            'contact' => 'c.contact_last_name',
            'address' => 'c.city',
            'affiliation' => 'c.affiliation',
            'athletes' => '(SELECT COUNT(*) FROM athletes a WHERE a.club_id = c.id)',
            default => 'c.name',
        };
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $statement = Database::connection()->prepare(
            'SELECT c.* FROM clubs c ORDER BY ' . $sortExpression . ' ' . $direction . ', c.id ASC'
            . ' LIMIT ? OFFSET ?'
        );
        $statement->bindValue(1, max(1, $limit), \PDO::PARAM_INT);
        $statement->bindValue(2, max(0, $offset), \PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn(array $row) => self::fromArray($row), $statement->fetchAll() ?: []);
    }
}
