<?php

declare(strict_types=1);

namespace App\Model;

final class Entry
{
    public function __construct(
        public readonly int $id,
        public readonly int $event_id,
        public readonly int $club_id,
        public readonly int $athlete_id,
        public readonly int $registration_option_id,
        public readonly string $registration_option_name,
        public readonly int $registration_fee_cents,
        public readonly string $created_at
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (int) ($data['event_id'] ?? 0),
            (int) ($data['club_id'] ?? 0),
            (int) ($data['athlete_id'] ?? 0),
            (int) ($data['registration_option_id'] ?? 0),
            (string) ($data['registration_option_name'] ?? ''),
            (int) ($data['registration_fee_cents'] ?? 0),
            (string) ($data['created_at'] ?? '')
        );
    }

    /** @return list<array<string, mixed>> */
    public static function findByEvent(
        int $eventId,
        ?int $clubId,
        ?bool $eventClosed = null
    ): array {
        return self::queryByEvent($eventId, $clubId, $eventClosed, null, 0, 'club_name', 'asc');
    }

    public static function countByEvent(int $eventId, ?int $clubId): int
    {
        $sql = 'SELECT COUNT(*) FROM entries WHERE event_id = ?';
        $parameters = [$eventId];
        if ($clubId !== null) {
            $sql .= ' AND club_id = ?';
            $parameters[] = $clubId;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public static function pageByEvent(
        int $eventId,
        ?int $clubId,
        ?bool $eventClosed,
        int $limit,
        int $offset,
        string $sort = 'club_name',
        string $direction = 'asc'
    ): array {
        return self::queryByEvent(
            $eventId,
            $clubId,
            $eventClosed,
            max(1, $limit),
            max(0, $offset),
            $sort,
            $direction
        );
    }

    /** @return list<array<string, mixed>> */
    private static function queryByEvent(
        int $eventId,
        ?int $clubId,
        ?bool $eventClosed,
        ?int $limit,
        int $offset,
        string $sort,
        string $direction
    ): array {
        $eventClosed ??= self::eventIsClosed($eventId);
        $lastName = self::athleteValueExpression($eventClosed, 'snapshot_last_name', 'last_name');
        $firstName = self::athleteValueExpression($eventClosed, 'snapshot_first_name', 'first_name');
        $gender = self::athleteValueExpression($eventClosed, 'snapshot_gender', 'gender');
        $weight = self::athleteValueExpression($eventClosed, 'snapshot_weight_kg', 'weight_kg');
        $belt = self::athleteValueExpression($eventClosed, 'snapshot_belt', 'belt');
        $membershipNumber = self::athleteValueExpression(
            $eventClosed,
            'snapshot_membership_number',
            'membership_number'
        );
        $birthDate = self::athleteValueExpression(
            $eventClosed,
            'snapshot_birth_date',
            'birth_date'
        );
        $type = $eventClosed ? 'en.snapshot_program' : "''";
        $weightCategory = $eventClosed ? 'en.snapshot_weight_category' : "''";

        $sql = sprintf(
            'SELECT en.id AS entry_id, c.id AS club_id,
                c.name AS club_name, c.federal_code AS federal_code,
                %s AS last_name,
                %s AS first_name,
                %s AS gender,
                %s AS weight_kg,
                %s AS belt,
                %s AS membership_number,
                %s AS birth_date,
                %s AS type,
                %s AS weight_category,
                e.name AS event_name, e.date AS event_date
            FROM entries en
            JOIN clubs c ON c.id = en.club_id
            JOIN athletes a ON a.id = en.athlete_id
            JOIN events e ON e.id = en.event_id
            WHERE en.event_id = ?',
            $lastName,
            $firstName,
            $gender,
            $weight,
            $belt,
            $membershipNumber,
            $birthDate,
            $type,
            $weightCategory
        );

        $params = [$eventId];
        if ($clubId !== null) {
            $sql .= ' AND c.id = ?';
            $params[] = $clubId;
        }

        $dynamicType = '(CASE WHEN (SUBSTR(' . $birthDate . ', 1, 4) + 0)'
            . ' >= (SUBSTR(e.date, 1, 4) + 0) - 11'
            . " THEN 'pre-competitive' ELSE 'competitive' END)";
        $dynamicWeightCategory = $eventClosed
            ? '(CASE WHEN ' . $weightCategory . " LIKE '+%' THEN 10000"
                . ' WHEN ' . $weightCategory . " LIKE '-%' THEN 0 ELSE 20000 END)"
                . ' + COALESCE(CAST(REPLACE(REPLACE(REPLACE('
                . $weightCategory . ", '+', ''), '-', ''), ' kg', '') AS DECIMAL(10, 2)), 0)"
            : $weight;
        $sortExpression = match ($sort) {
            'federal_code' => 'federal_code',
            'last_name' => 'last_name',
            'first_name' => 'first_name',
            'gender' => 'gender',
            'birth_date' => 'birth_date',
            'weight_kg' => 'weight_kg',
            'belt' => self::beltSortExpression($belt),
            'membership_number' => 'membership_number',
            'type' => $eventClosed ? $type : $dynamicType,
            'weight_category' => $dynamicWeightCategory,
            default => 'club_name',
        };
        $sortDirection = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $sql .= ' ORDER BY ' . $sortExpression . ' ' . $sortDirection;
        if ($sortExpression !== 'club_name' || $sortDirection !== 'ASC') {
            $sql .= ', club_name ASC';
        }
        if ($sortExpression !== 'last_name') {
            $sql .= ', last_name ASC';
        }
        if ($sortExpression !== 'first_name') {
            $sql .= ', first_name ASC';
        }
        $sql .= ', a.id ASC';
        if ($limit !== null) {
            $sql .= ' LIMIT ? OFFSET ?';
        }

        $stmt = Database::connection()->prepare($sql);
        if ($limit === null) {
            $stmt->execute($params);
        } else {
            foreach ($params as $index => $parameter) {
                $stmt->bindValue($index + 1, $parameter, \PDO::PARAM_INT);
            }
            $stmt->bindValue(count($params) + 1, $limit, \PDO::PARAM_INT);
            $stmt->bindValue(count($params) + 2, $offset, \PDO::PARAM_INT);
            $stmt->execute();
        }
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            if (!$eventClosed || empty($row['weight_category'])) {
                $category = JudoCategory::calculate(
                    (string) ($row['birth_date'] ?? ''),
                    (string) ($row['gender'] ?? ''),
                    (float) ($row['weight_kg'] ?? 0.0),
                    Athlete::eventYearFromDate((string) ($row['event_date'] ?? ''))
                );
                $row['type'] = $category['type'];
                $row['weight_category'] = $category['weight_category'];
            }
        }
        unset($row);

        return $rows;
    }

    private static function athleteValueExpression(
        bool $eventClosed,
        string $snapshotColumn,
        string $athleteColumn
    ): string {
        if (!$eventClosed) {
            return 'a.' . $athleteColumn;
        }

        return sprintf('COALESCE(en.%s, a.%s)', $snapshotColumn, $athleteColumn);
    }

    private static function beltSortExpression(string $expression): string
    {
        $order = array_map(
            static fn(Belt $belt, int $position): string => sprintf(
                "WHEN '%s' THEN %d",
                $belt->value,
                $position
            ),
            Belt::cases(),
            array_keys(Belt::cases())
        );

        return 'CASE ' . $expression . ' ' . implode(' ', $order) . ' ELSE 999 END';
    }

    private static function eventIsClosed(int $eventId): bool
    {
        $statement = Database::connection()->prepare('SELECT closed FROM events WHERE id = ?');
        $statement->execute([$eventId]);

        return !empty($statement->fetchColumn());
    }

    /** @return list<array<string, mixed>> */
    public static function findClubsByEvent(int $eventId, ?int $clubId): array
    {
        $sql = 'SELECT DISTINCT c.id, c.name AS club_name, c.federal_code AS federal_code
                FROM entries en
                JOIN clubs c ON c.id = en.club_id
                WHERE en.event_id = ?';
        $params = [$eventId];

        if ($clubId !== null) {
            $sql .= ' AND c.id = ?';
            $params[] = $clubId;
        }

        $sql .= ' ORDER BY c.name';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array{id: int, name: string, date: string}> */
    public static function eventsByClub(int $clubId, int $limit): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT e.id, e.name, e.date
             FROM entries en
             JOIN events e ON e.id = en.event_id
             WHERE en.club_id = ?
             ORDER BY e.date DESC, e.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $clubId, \PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn(array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'date' => (string) $row['date'],
            ],
            $stmt->fetchAll() ?: []
        );
    }

    /**
     * @param list<int> $athleteIds
     * @return array<int, int>
     */
    public static function registrationCountsByAthletes(
        int $clubId,
        array $athleteIds,
        ?int $eventId = null
    ): array {
        $athleteIds = array_values(array_unique(array_filter(
            array_map('intval', $athleteIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($athleteIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($athleteIds), '?'));
        $sql = 'SELECT athlete_id, COUNT(*) AS registrations
                FROM entries
                WHERE club_id = ? AND athlete_id IN (' . $placeholders . ')';
        $parameters = [$clubId, ...$athleteIds];
        if ($eventId !== null && $eventId > 0) {
            $sql .= ' AND event_id = ?';
            $parameters[] = $eventId;
        }
        $sql .= ' GROUP BY athlete_id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($parameters);
        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int) $row['athlete_id']] = (int) $row['registrations'];
        }

        return $counts;
    }

    /**
     * @param list<int> $eventIds
     * @return array<int, array{clubs: int, athletes: int}>
     */
    public static function countsByEventIds(array $eventIds): array
    {
        $eventIds = array_values(array_unique(array_filter(
            array_map('intval', $eventIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($eventIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($eventIds), '?'));
        $stmt = Database::connection()->prepare(
            'SELECT event_id, COUNT(DISTINCT club_id) AS clubs, COUNT(athlete_id) AS athletes
             FROM entries
             WHERE event_id IN (' . $placeholders . ')
             GROUP BY event_id'
        );
        $stmt->execute($eventIds);
        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int) $row['event_id']] = [
                'clubs' => (int) $row['clubs'],
                'athletes' => (int) $row['athletes'],
            ];
        }

        return $counts;
    }

    public static function register(
        int $eventId,
        int $clubId,
        int $athleteId,
        int $registrationOptionId,
        string $registrationDate
    ): EntryRegistrationResult {
        $repository = new EntryRegistrationRepository(Database::connection());

        return $repository->register(
            $eventId,
            $clubId,
            $athleteId,
            $registrationOptionId,
            $registrationDate
        );
    }

    public static function unregister(
        int $eventId,
        int $clubId,
        int $athleteId,
        string $registrationDate
    ): EntryRegistrationResult {
        $repository = new EntryRegistrationRepository(Database::connection());

        return $repository->unregister($eventId, $clubId, $athleteId, $registrationDate);
    }

    /** @return list<int> */
    public static function findByClubEvent(int $eventId, int $clubId): array
    {
        $stmt = Database::connection()->prepare('SELECT athlete_id FROM entries WHERE event_id = ? AND club_id = ?');
        $stmt->execute([$eventId, $clubId]);

        return array_column($stmt->fetchAll(), 'athlete_id');
    }

    /**
     * @return array<int, array{
     *     athlete_id:int,
     *     athlete_name:string,
     *     option_id:int,
     *     option_name:string,
     *     fee_cents:int
     * }>
     */
    public static function enrollmentDetailsByClubEvent(int $eventId, int $clubId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT entry_record.athlete_id,
                    athlete.last_name,
                    athlete.first_name,
                    entry_record.registration_option_id,
                    entry_record.registration_option_name,
                    entry_record.registration_fee_cents
             FROM entries AS entry_record
             JOIN athletes AS athlete ON athlete.id = entry_record.athlete_id
             WHERE entry_record.event_id = ? AND entry_record.club_id = ?'
        );
        $statement->execute([$eventId, $clubId]);

        $details = [];
        foreach ($statement->fetchAll() ?: [] as $row) {
            $athleteId = (int) $row['athlete_id'];
            $details[$athleteId] = [
                'athlete_id' => $athleteId,
                'athlete_name' => (string) $row['last_name'] . ' ' . (string) $row['first_name'],
                'option_id' => (int) $row['registration_option_id'],
                'option_name' => (string) $row['registration_option_name'],
                'fee_cents' => (int) $row['registration_fee_cents'],
            ];
        }

        return $details;
    }
}
