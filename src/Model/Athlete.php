<?php

declare(strict_types=1);

namespace App\Model;

final class Athlete
{
    public function __construct(
        public readonly int $id,
        public readonly int $club_id,
        public readonly string $last_name,
        public readonly string $first_name,
        public readonly string $gender,
        public readonly string $birth_date,
        public readonly ?float $weight_kg,
        public readonly string $belt,
        public readonly ?string $membership_number,
        public readonly ?string $notes
    ) {
    }

    /**
     * Returns the belt as a Belt enum instance, or null if invalid.
     */
    public function beltEnum(): ?Belt
    {
        return Belt::tryFromValue($this->belt);
    }

    /**
     * Returns the localized belt label.
     */
    public function beltLabel(?string $locale = null): string
    {
        $enum = $this->beltEnum();

        return $enum?->label($locale) ?? $this->belt;
    }

    /**
     * Returns the gender as a Gender enum instance, or null if invalid.
     */
    public function genderEnum(): ?Gender
    {
        return Gender::tryFromValue($this->gender);
    }

    /**
     * Returns the localized gender label.
     */
    public function genderLabel(?string $locale = null): string
    {
        $enum = $this->genderEnum();

        return $enum?->label($locale) ?? $this->gender;
    }

    /**
     * Returns the localized gender label with a UTF-8 icon, e.g. "♂ Maschio".
     */
    public function genderIconLabel(?string $locale = null): string
    {
        $enum = $this->genderEnum();

        return $enum?->iconLabel($locale) ?? $this->gender;
    }

    /**
     * Returns the event year extracted from the given date, or a default.
     */
    public static function eventYearFromDate(?string $date = null, int $default = 0): int
    {
        if ($date !== null && $date !== '' && preg_match('/^\d{4}/', $date, $m)) {
            return (int) $m[0];
        }
        if ($default === 0) {
            $default = (int) date('Y');
        }
        return $default;
    }

    /**
     * Returns the localized age class label, computed from the birth date.
     */
    public function ageClassLabel(string $locale = 'it', ?string $eventDate = null): string
    {
        $ac = $this->ageClassModel($locale, $eventDate);
        return $ac?->label($locale) ?? '';
    }

    /**
     * Returns the AgeClass model instance computed from the birth date.
     */
    public function ageClassModel(string $locale = 'it', ?string $eventDate = null): ?AgeClass
    {
        $birthYear = JudoCategory::extractBirthYear($this->birth_date);
        if ($birthYear === null) {
            return null;
        }

        $eventYear = self::eventYearFromDate($eventDate);
        $result = AgeClass::calculate($birthYear, $eventYear, $locale);

        if ($result['key'] === 'out_of_range') {
            return null;
        }

        return AgeClass::findByAgeBelow($result['age_below'], $locale);
    }

    /** @return array{age_below: int|null, type: string, weight_category: string} */
    public function categoryForEventDate(?string $eventDate = null): array
    {
        return JudoCategory::calculate(
            $this->birth_date,
            $this->gender,
            $this->weight_kg ?? 0.0,
            self::eventYearFromDate($eventDate)
        );
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (int) ($data['club_id'] ?? 0),
            (string) ($data['last_name'] ?? ''),
            (string) ($data['first_name'] ?? ''),
            (string) ($data['gender'] ?? ''),
            (string) ($data['birth_date'] ?? ''),
            isset($data['weight_kg']) && $data['weight_kg'] !== ''
                ? (float) $data['weight_kg']
                : null,
            (string) ($data['belt'] ?? ''),
            isset($data['membership_number']) && $data['membership_number'] !== ''
                ? (string) $data['membership_number']
                : null,
            isset($data['notes']) && $data['notes'] !== ''
                ? (string) $data['notes']
                : null,
        );
    }

    /** @return list<self> */
    public static function findByClub(int $clubId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM athletes WHERE club_id = ? ORDER BY last_name, first_name');
        $stmt->execute([$clubId]);
        $rows = $stmt->fetchAll();

        return array_map(fn(array $row) => self::fromArray($row), $rows ?: []);
    }

    public static function countByClub(int $clubId): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM athletes WHERE club_id = ?');
        $stmt->execute([$clubId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param list<int> $clubIds
     * @return array<int, int>
     */
    public static function countsByClubIds(array $clubIds): array
    {
        $clubIds = array_values(array_unique(array_filter(
            array_map('intval', $clubIds),
            static fn(int $clubId): bool => $clubId > 0
        )));
        if ($clubIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($clubIds), '?'));
        $statement = Database::connection()->prepare(
            "SELECT club_id, COUNT(*) AS athlete_count
             FROM athletes
             WHERE club_id IN ($placeholders)
             GROUP BY club_id"
        );
        $statement->execute($clubIds);

        $counts = [];
        foreach ($statement->fetchAll() ?: [] as $row) {
            $counts[(int) $row['club_id']] = (int) $row['athlete_count'];
        }

        return $counts;
    }

    /** @return list<self> */
    public static function pageByClub(
        int $clubId,
        int $limit,
        int $offset,
        string $sort = 'athlete',
        string $direction = 'asc',
        ?int $eventId = null
    ): array {
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        if ($sort === 'athlete' && $direction === 'ASC') {
            $stmt = Database::connection()->prepare(
                'SELECT * FROM athletes
                 WHERE club_id = ?
                 ORDER BY last_name ASC, first_name ASC, id ASC
                 LIMIT ? OFFSET ?'
            );
            $stmt->bindValue(1, $clubId, \PDO::PARAM_INT);
            $stmt->bindValue(2, max(1, $limit), \PDO::PARAM_INT);
            $stmt->bindValue(3, max(0, $offset), \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            return array_map(fn(array $row) => self::fromArray($row), $rows ?: []);
        }

        $sortParameters = [];
        $sortExpression = match ($sort) {
            'gender' => 'a.gender',
            'birth', 'age_class' => 'a.birth_date',
            'weight', 'weight_category' => 'a.weight_kg',
            'belt' => 'a.belt',
            'membership_number' => 'a.membership_number',
            'notes' => 'a.notes',
            'registrations' => self::registrationSortExpression($eventId, $sortParameters),
            'current_option' => self::currentOptionSortExpression($eventId, $sortParameters),
            default => 'a.last_name',
        };
        $sortDirection = $sort === 'age_class'
            ? ($direction === 'ASC' ? 'DESC' : 'ASC')
            : $direction;
        $secondaryOrder = $sort === 'athlete'
            ? ', a.first_name ' . $direction
            : ', a.last_name ASC, a.first_name ASC';
        $stmt = Database::connection()->prepare(
            'SELECT a.* FROM athletes a
             WHERE a.club_id = ?
             ORDER BY ' . $sortExpression . ' ' . $sortDirection . $secondaryOrder . ', a.id ASC
             LIMIT ? OFFSET ?'
        );
        $parameter = 1;
        $stmt->bindValue($parameter++, $clubId, \PDO::PARAM_INT);
        foreach ($sortParameters as $sortParameter) {
            $stmt->bindValue($parameter++, $sortParameter, \PDO::PARAM_INT);
        }
        $stmt->bindValue($parameter++, max(1, $limit), \PDO::PARAM_INT);
        $stmt->bindValue($parameter, max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn(array $row) => self::fromArray($row), $rows ?: []);
    }

    /** @param list<int> $parameters */
    private static function registrationSortExpression(?int $eventId, array &$parameters): string
    {
        $eventFilter = '';
        if ($eventId !== null && $eventId > 0) {
            $eventFilter = ' AND en.event_id = ?';
            $parameters[] = $eventId;
        }

        return '(SELECT COUNT(*) FROM entries en'
            . ' WHERE en.club_id = a.club_id AND en.athlete_id = a.id'
            . $eventFilter . ')';
    }

    /** @param list<int> $parameters */
    private static function currentOptionSortExpression(?int $eventId, array &$parameters): string
    {
        if ($eventId === null || $eventId <= 0) {
            return "''";
        }
        $parameters[] = $eventId;

        return '(SELECT en.registration_option_name FROM entries en'
            . ' WHERE en.club_id = a.club_id AND en.athlete_id = a.id AND en.event_id = ?'
            . ' LIMIT 1)';
    }

    public static function findById(int $id, int $clubId): ?self
    {
        $stmt = Database::connection()->prepare('SELECT * FROM athletes WHERE id = ? AND club_id = ?');
        $stmt->execute([$id, $clubId]);
        $row = $stmt->fetch();

        return $row ? self::fromArray($row) : null;
    }

    /**
     * Accepts English-named keys and maps them to DB columns for insertion.
     * @param array<string,mixed> $data
     */
    public static function add(array $data): self
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO athletes (club_id, last_name, first_name, gender, birth_date, weight_kg, belt, membership_number, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $data['club_id'] ?? $data['clubId'] ?? 0,
            $data['last_name'] ?? '',
            $data['first_name'] ?? '',
            $data['gender'] ?? '',
            $data['birth_date'] ?? '',
            $data['weight_kg'] ?? null,
            $data['belt'] ?? '',
            $data['membership_number'] ?? null,
            $data['notes'] ?? null,
        ]);

        $row = Database::connection()->query('SELECT * FROM athletes WHERE id = LAST_INSERT_ID()')->fetch();

        return self::fromArray($row);
    }

    /** @param array<string,mixed> $data */
    public function update(array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE athletes SET last_name = ?, first_name = ?, gender = ?, birth_date = ?, weight_kg = ?, belt = ?, membership_number = ?, notes = ? WHERE id = ? AND club_id = ?'
        );

        $stmt->execute([
            $data['last_name'] ?? '',
            $data['first_name'] ?? '',
            $data['gender'] ?? '',
            $data['birth_date'] ?? '',
            $data['weight_kg'] ?? null,
            $data['belt'] ?? '',
            $data['membership_number'] ?? null,
            $data['notes'] ?? null,
            $this->id,
            $this->club_id,
        ]);
    }

    public static function remove(int $id, int $clubId): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM athletes WHERE id = ? AND club_id = ?'
        );
        $statement->execute([$id, $clubId]);
    }
}
