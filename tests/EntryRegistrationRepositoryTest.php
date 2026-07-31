<?php

declare(strict_types=1);

namespace Tests;

use App\Model\Database;
use App\Model\EntryRegistrationRepository;
use App\Model\EntryRegistrationResult;
use PDO;
use ReflectionProperty;
use PHPUnit\Framework\TestCase;

final class EntryRegistrationRepositoryTest extends TestCase
{
    private ReflectionProperty $databaseConnection;
    private ?PDO $originalConnection;
    private PDO $database;

    protected function setUp(): void
    {
        $this->databaseConnection = new ReflectionProperty(Database::class, 'pdo');
        $connection = $this->databaseConnection->getValue();
        self::assertTrue($connection === null || $connection instanceof PDO);
        $this->originalConnection = $connection;

        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema();
        $this->databaseConnection->setValue(null, $this->database);
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
    }

    public function testOwnAthleteIsRegisteredByOneConstrainedStatement(): void
    {
        $this->insertAthlete(301, 201);
        $this->insertEvent(101, false);

        $result = (new EntryRegistrationRepository($this->database))->register(101, 201, 301, 501, '2026-06-28');

        self::assertSame(EntryRegistrationResult::Registered, $result);
        self::assertSame(
            ['Standard', 1500],
            $this->database->query(
                'SELECT registration_option_name, registration_fee_cents FROM entries'
            )->fetch(PDO::FETCH_NUM)
        );
    }

    public function testForeignAthleteIsRejectedWithoutAnInsert(): void
    {
        $this->insertAthlete(302, 202);
        $this->insertEvent(101, false);

        $result = (new EntryRegistrationRepository($this->database))->register(101, 201, 302, 501, '2026-06-28');

        self::assertSame(EntryRegistrationResult::AthleteRejected, $result);
    }

    public function testMissingAthleteIsRejectedWithoutAnInsert(): void
    {
        $this->insertEvent(101, false);

        $result = (new EntryRegistrationRepository($this->database))->register(101, 201, 999, 501, '2026-06-28');

        self::assertSame(EntryRegistrationResult::AthleteRejected, $result);
    }

    public function testOptionFromOutsideTheEventIsRejectedWithoutAnInsert(): void
    {
        $this->insertAthlete(301, 201);
        $this->insertEvent(101, false);
        $this->database->exec(
            "INSERT INTO event_registration_options (
                id, event_id, name, fee_cents, is_default, is_active, sort_order
             ) VALUES (999, 202, 'Foreign option', 2500, 1, 1, 0)"
        );

        $result = (new EntryRegistrationRepository($this->database))->register(
            101,
            201,
            301,
            999,
            '2026-06-28'
        );

        self::assertSame(EntryRegistrationResult::AthleteRejected, $result);
        self::assertSame(0, (int) $this->database->query('SELECT COUNT(*) FROM entries')->fetchColumn());
    }

    public function testDuplicateConstraintViolationReturnsAlreadyRegistered(): void
    {
        $this->insertAthlete(301, 201);
        $this->insertEvent(101, false);
        $this->insertEntry(101, 201, 301);

        $result = (new EntryRegistrationRepository($this->database))->register(101, 201, 301, 501, '2026-06-28');

        self::assertSame(EntryRegistrationResult::AlreadyRegistered, $result);
    }

    public function testCapacityExceededReturnsCapacityExceededResult(): void
    {
        $this->insertAthlete(301, 201);
        $this->insertEvent(101, false, maxParticipants: 10);
        $this->insertEntries(101, 201, 10);

        $result = (new EntryRegistrationRepository($this->database))->register(101, 201, 301, 501, '2026-06-28');

        self::assertSame(EntryRegistrationResult::CapacityExceeded, $result);
    }

    public function testNoLimitAllowsRegistration(): void
    {
        $this->insertAthlete(301, 201);
        $this->insertEvent(101, false);

        $result = (new EntryRegistrationRepository($this->database))->register(101, 201, 301, 501, '2026-06-28');

        self::assertSame(EntryRegistrationResult::Registered, $result);
    }

    public function testPrecompetitiveEventRejectsCompetitiveAthlete(): void
    {
        $this->insertAthlete(301, 201, '2014-12-31');
        $this->insertEvent(101, false, '2026-01-01', type: 'only_precompetitive');

        $result = (new EntryRegistrationRepository($this->database))->register(101, 201, 301, 501, '2025-12-31');

        self::assertSame(EntryRegistrationResult::AthleteRejected, $result);
    }

    public function testCompetitiveEventRejectsPrecompetitiveAthlete(): void
    {
        $this->insertAthlete(301, 201, '2015-01-01');
        $this->insertEvent(101, false, '2026-06-29', type: 'only_competitive');

        $result = (new EntryRegistrationRepository($this->database))->register(101, 201, 301, 501, '2026-06-28');

        self::assertSame(EntryRegistrationResult::AthleteRejected, $result);
    }

    public function testCompetitiveEligibilityUsesBirthYearRatherThanExactBirthday(): void
    {
        $this->insertAthlete(301, 201, '2014-12-31');
        $this->insertEvent(101, false, '2026-01-01', type: 'only_competitive');

        $result = (new EntryRegistrationRepository($this->database))->register(101, 201, 301, 501, '2025-12-31');

        self::assertSame(EntryRegistrationResult::Registered, $result);
    }

    public function testOpenEventAthleteIsUnsubscribed(): void
    {
        $this->insertEvent(101, false);
        $this->insertEntry(101, 201, 301);

        $result = (new EntryRegistrationRepository($this->database))->unregister(101, 201, 301, '2026-06-28');

        self::assertSame(EntryRegistrationResult::Unsubscribed, $result);
    }

    public function testClosedEventReturnsUnsubscribeFailed(): void
    {
        $this->insertEvent(101, true);

        $result = (new EntryRegistrationRepository($this->database))->unregister(101, 201, 301, '2026-06-28');

        self::assertSame(EntryRegistrationResult::UnsubscribeFailed, $result);
    }

    public function testNonexistentEntryReturnsUnsubscribeFailed(): void
    {
        $this->insertEvent(101, false);

        $result = (new EntryRegistrationRepository($this->database))->unregister(101, 201, 301, '2026-06-28');

        self::assertSame(EntryRegistrationResult::UnsubscribeFailed, $result);
    }

    public function testBaselineSchemaRetainsTheEntryUniqueConstraint(): void
    {
        $schema = file_get_contents(dirname(__DIR__) . '/migrations/20260630_000000_create_schema.sql');

        self::assertIsString($schema);
        self::assertStringContainsString(
            'UNIQUE KEY unique_entry (event_id, club_id, athlete_id)',
            $schema
        );
    }

    private function createSchema(): void
    {
        $this->database->exec(
            'CREATE TABLE clubs (id INTEGER PRIMARY KEY, federal_code TEXT NOT NULL, name TEXT NOT NULL)'
        );
        $this->database->exec(
            'CREATE TABLE events (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                date TEXT NOT NULL,
                location TEXT,
                registration_deadline TEXT,
                type TEXT NOT NULL,
                max_participants INTEGER,
                published INTEGER NOT NULL,
                closed INTEGER NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE athletes (
                id INTEGER PRIMARY KEY,
                club_id INTEGER NOT NULL,
                last_name TEXT NOT NULL,
                first_name TEXT NOT NULL,
                gender TEXT NOT NULL,
                birth_date TEXT NOT NULL,
                weight_kg REAL NOT NULL,
                belt TEXT
            )'
        );
        $this->database->exec(
            'CREATE TABLE entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                club_id INTEGER NOT NULL,
                athlete_id INTEGER NOT NULL,
                registration_option_id INTEGER NOT NULL DEFAULT 501,
                registration_option_name TEXT NOT NULL DEFAULT \'Standard\',
                registration_fee_cents INTEGER NOT NULL DEFAULT 1500,
                UNIQUE (event_id, club_id, athlete_id)
            )'
        );
        $this->database->exec(
            'CREATE TABLE event_registration_options (
                id INTEGER PRIMARY KEY,
                event_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                fee_cents INTEGER NOT NULL,
                is_default INTEGER NOT NULL,
                is_active INTEGER NOT NULL,
                sort_order INTEGER NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE event_registration_exceptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                club_id INTEGER NOT NULL,
                UNIQUE (event_id, club_id)
            )'
        );
    }

    private function insertClub(int $id, string $code, string $name): void
    {
        $this->database->prepare(
            'INSERT INTO clubs (id, federal_code, name) VALUES (?, ?, ?)'
        )->execute([$id, $code, $name]);
    }

    private function insertAthlete(int $athleteId, int $clubId, string $birthDate = '2010-01-01'): void
    {
        $this->database->prepare(
            'INSERT INTO athletes (id, club_id, last_name, first_name, gender, birth_date, weight_kg, belt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$athleteId, $clubId, 'Last', 'First', 'M', $birthDate, 50.0, 'white']);
    }

    private function insertEvent(
        int $eventId,
        bool $closed,
        string $date = '2026-06-29',
        ?string $deadline = '2026-06-28',
        ?int $maxParticipants = null,
        string $type = 'only_competitive'
    ): void {
        $this->database->prepare(
            'INSERT INTO events (id, name, date, location, registration_deadline, type, max_participants, published, closed)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $eventId,
            'Synthetic Event',
            $date,
            'Venue',
            $deadline,
            $type,
            $maxParticipants,
            1,
            $closed ? 1 : 0
        ]);
        $this->database->prepare(
            'INSERT OR IGNORE INTO event_registration_options (
                id, event_id, name, fee_cents, is_default, is_active, sort_order
             ) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([501, $eventId, 'Standard', 1500, 1, 1, 0]);
    }

    private function insertEntry(int $eventId, int $clubId, int $athleteId): void
    {
        $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        )->execute([$eventId, $clubId, $athleteId]);
    }

    private function insertEntries(int $eventId, int $clubId, int $count): void
    {
        $stmt = $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        );
        for ($i = 0; $i < $count; $i++) {
            $stmt->execute([$eventId, $clubId, 400 + $i]);
        }
    }
}
