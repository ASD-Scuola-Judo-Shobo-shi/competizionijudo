<?php

declare(strict_types=1);

namespace Tests;

use App\Model\Athlete;
use App\Model\Club;
use App\Model\Database;
use App\Model\Entry;
use App\Model\Event;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ListQueryBoundsTest extends TestCase
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

    public function testUpcomingEventsAreOpenPublishedCurrentAndLimited(): void
    {
        $this->insertEvent(101, 'Past', '2026-06-28', true, false);
        $this->insertEvent(102, 'Today', '2026-06-29', true, false);
        $this->insertEvent(103, 'Future', '2026-07-01', true, false);
        $this->insertEvent(104, 'Closed', '2026-07-02', true, true);
        $this->insertEvent(105, 'Draft', '2026-07-03', false, false);
        $this->insertEvent(106, 'Later', '2026-07-04', true, false);

        $events = Event::upcomingPublished('2026-06-29', 2);

        self::assertSame([102, 103], array_map(static fn(Event $event): int => $event->id, $events));
        self::assertSame(
            [103, 106],
            array_map(
                static fn(Event $event): int => $event->id,
                Event::nextUpcomingPublished(102, '2026-06-29', 2)
            )
        );
    }

    public function testClubAndAthletePagesBoundReturnedRows(): void
    {
        for ($id = 1; $id <= 55; $id++) {
            $this->insertClub($id, sprintf('Club %02d', $id));
            $this->insertAthlete(1000 + $id, 1, sprintf('Athlete %02d', $id));
        }

        self::assertSame(55, Club::count());
        self::assertSame(
            [51, 52, 53, 54, 55],
            array_map(static fn(Club $club): int => $club->id, Club::page(50, 50))
        );
        self::assertSame(55, Athlete::countByClub(1));
        self::assertSame(
            [1051, 1052, 1053, 1054, 1055],
            array_map(static fn(Athlete $athlete): int => $athlete->id, Athlete::pageByClub(1, 50, 50))
        );
    }

    public function testEntryAggregatesAreRestrictedToDisplayedIdentifiers(): void
    {
        $this->insertClub(201, 'Synthetic Club');
        $this->insertEvent(101, 'Displayed', '2026-07-01', true, false);
        $this->insertEvent(102, 'Other', '2026-07-02', true, false);
        $this->insertAthlete(301, 201, 'First');
        $this->insertAthlete(302, 201, 'Second');
        $this->insertEntry(1, 101, 201, 301);
        $this->insertEntry(2, 101, 201, 302);
        $this->insertEntry(3, 102, 201, 301);

        self::assertSame([101 => ['clubs' => 1, 'athletes' => 2]], Entry::countsByEventIds([101]));
        self::assertSame([301 => 1], Entry::registrationCountsByAthletes(201, [301], 101));
        self::assertSame([], Entry::countsByEventIds([]));
        self::assertSame([], Entry::registrationCountsByAthletes(201, [], 101));
    }

    private function createSchema(): void
    {
        $this->database->exec(
            'CREATE TABLE events (
                id INTEGER PRIMARY KEY, name TEXT NOT NULL, date TEXT NOT NULL,
                location TEXT, organizer TEXT, registration_deadline TEXT, type TEXT,
                description TEXT, notes TEXT, poster_file TEXT, info_file TEXT,
                published INTEGER NOT NULL, closed INTEGER NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE clubs (
                id INTEGER PRIMARY KEY, federal_code TEXT NOT NULL, name TEXT NOT NULL,
                email TEXT NOT NULL, phone TEXT NOT NULL, contact_first_name TEXT NOT NULL,
                contact_last_name TEXT NOT NULL, contact_phone TEXT NOT NULL, contact_email TEXT,
                organization TEXT NOT NULL, recovery_email TEXT NOT NULL, password_hash TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE athletes (
                id INTEGER PRIMARY KEY, club_id INTEGER NOT NULL, last_name TEXT NOT NULL,
                first_name TEXT NOT NULL, gender TEXT NOT NULL, date_of_birth TEXT NOT NULL,
                weight_kg REAL NOT NULL, belt TEXT NOT NULL, program TEXT NOT NULL,
                weight_category TEXT NOT NULL, membership_number TEXT, notes TEXT
            )'
        );
        $this->database->exec(
            'CREATE TABLE entries (
                id INTEGER PRIMARY KEY, event_id INTEGER NOT NULL, club_id INTEGER NOT NULL,
                athlete_id INTEGER NOT NULL, created_at TEXT NOT NULL
            )'
        );
    }

    private function insertEvent(int $id, string $name, string $date, bool $published, bool $closed): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO events VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $id, $name, $date, 'Synthetic Venue', 'Synthetic Organizer', $date,
            'only_competitive', null, null, null, null, (int) $published, (int) $closed,
        ]);
    }

    private function insertClub(int $id, string $name): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO clubs VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $id, 'CODE-' . $id, $name, 'club' . $id . '@example.test', '',
            'Synthetic', 'Contact', '', null, 'SYNTHETIC',
            'recovery' . $id . '@example.test', 'synthetic-hash',
        ]);
    }

    private function insertAthlete(int $id, int $clubId, string $lastName): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO athletes VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $id, $clubId, $lastName, 'Synthetic', 'M', '2010-01-01', 50.0,
            'white', 'SYNTHETIC', 'SYNTHETIC', 'MEMBER-' . $id, null,
        ]);
    }

    private function insertEntry(int $id, int $eventId, int $clubId, int $athleteId): void
    {
        $statement = $this->database->prepare('INSERT INTO entries VALUES (?, ?, ?, ?, ?)');
        $statement->execute([$id, $eventId, $clubId, $athleteId, '2026-06-29 12:00:00']);
    }
}
