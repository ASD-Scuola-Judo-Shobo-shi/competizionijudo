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
        public readonly string $date_of_birth,
        public readonly float $weight_kg,
        public readonly string $belt,
        public readonly string $program,
        public readonly string $weight_category,
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
        $birthYear = JudoCategory::extractBirthYear($this->date_of_birth);
        if ($birthYear === null) {
            return null;
        }

        $eventYear = self::eventYearFromDate($eventDate);
        $result = AgeClass::calculate($birthYear, $eventYear, $locale);
        return AgeClass::findByAgeBelow($result['age_below'], $locale);
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
            (string) ($data['date_of_birth'] ?? ''),
            (float) ($data['weight_kg'] ?? 0.0),
            (string) ($data['belt'] ?? ''),
            (string) ($data['program'] ?? ''),
            (string) ($data['weight_category'] ?? ''),
            $data['membership_number'] !== '' ? (string) $data['membership_number'] : null,
            $data['notes'] !== '' ? (string) $data['notes'] : null,
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

    /** @return list<self> */
    public static function pageByClub(int $clubId, int $limit, int $offset): array
    {
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
            'INSERT INTO athletes (club_id, last_name, first_name, gender, date_of_birth, weight_kg, belt, program, weight_category, membership_number, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $data['club_id'] ?? $data['clubId'] ?? 0,
            $data['last_name'] ?? '',
            $data['first_name'] ?? '',
            $data['gender'] ?? '',
            $data['date_of_birth'] ?? '',
            $data['weight_kg'] ?? 0.0,
            $data['belt'] ?? '',
            $data['program'] ?? '',
            $data['weight_category'] ?? '',
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
            'UPDATE athletes SET last_name = ?, first_name = ?, gender = ?, date_of_birth = ?, weight_kg = ?, belt = ?, program = ?, weight_category = ?, membership_number = ?, notes = ? WHERE id = ? AND club_id = ?'
        );

        $stmt->execute([
            $data['last_name'] ?? '',
            $data['first_name'] ?? '',
            $data['gender'] ?? '',
            $data['date_of_birth'] ?? '',
            $data['weight_kg'] ?? 0.0,
            $data['belt'] ?? '',
            $data['program'] ?? '',
            $data['weight_category'] ?? '',
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
