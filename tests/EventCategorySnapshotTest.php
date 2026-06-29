<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\AdminController;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Model\Database;
use App\Model\Entry;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class EventCategorySnapshotTest extends TestCase
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
        $this->createSchemaAndData();
        $this->databaseConnection->setValue(null, $this->database);
        Session::destroy();
        Session::start();
        Session::set('is_admin', true);
        Localization::setLocale('it');
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
        Session::destroy();
    }

    public function testLiveCategoriesUseEachEventYearAndClosedOutputUsesSnapshot(): void
    {
        self::assertSame('-40 kg', Entry::findByEvent(101, 201)[0]['weight_category']);
        self::assertSame('-38 kg', Entry::findByEvent(102, 201)[0]['weight_category']);

        $response = $this->closeEventRequest();
        self::assertSame(302, $response->status());
        self::assertSame(1, (int) $this->database->query('SELECT closed FROM events WHERE id = 101')->fetchColumn());

        $this->database->exec(
            "UPDATE athletes
             SET last_name = 'Changed', weight_kg = 50
             WHERE id = 301"
        );

        $closed = Entry::findByEvent(101, 201)[0];
        $open = Entry::findByEvent(102, 201)[0];
        self::assertSame('Original', $closed['last_name']);
        self::assertSame(38.0, $closed['weight_kg']);
        self::assertSame('-40 kg', $closed['weight_category']);
        self::assertSame('Changed', $open['last_name']);
        self::assertSame(50.0, $open['weight_kg']);
        self::assertSame('-50 kg', $open['weight_category']);
        self::assertNotEmpty(
            $this->database->query('SELECT snapshot_at FROM entries WHERE event_id = 101')->fetchColumn()
        );

        $columns = $this->database->query('PRAGMA table_info(athletes)')->fetchAll(PDO::FETCH_COLUMN, 1);
        self::assertNotContains('program', $columns);
        self::assertNotContains('weight_category', $columns);
    }

    public function testClosureRollsBackWhenSnapshotConsolidationFails(): void
    {
        $this->database->exec(
            "CREATE TRIGGER fail_snapshot
             BEFORE UPDATE OF snapshot_last_name ON entries
             BEGIN SELECT RAISE(FAIL, 'Synthetic snapshot failure'); END"
        );

        $response = $this->closeEventRequest();

        self::assertSame(200, $response->status());
        self::assertSame(0, (int) $this->database->query('SELECT closed FROM events WHERE id = 101')->fetchColumn());
        self::assertNull(
            $this->database->query('SELECT snapshot_at FROM entries WHERE event_id = 101')->fetchColumn()
        );
    }

    private function closeEventRequest(): \App\Core\Response
    {
        $request = new Request('POST', '/admin_add_event.php', [], [
            'csrf_token' => csrf_token(),
            'event_id' => '101',
            'name' => '2026 Event',
            'date' => '2026-07-01',
            'location' => 'Synthetic Venue',
            'organizer' => 'Synthetic Organizer',
            'registration_deadline' => '2026-06-30',
            'type' => 'only_competitive',
            'description' => '',
            'notes' => '',
            'published' => '1',
            'closed' => '1',
        ]);

        return (new AdminController(new View(dirname(__DIR__) . '/views'), $request))->addEvent($request);
    }

    private function createSchemaAndData(): void
    {
        $this->database->exec(
            'CREATE TABLE clubs (
                id INTEGER PRIMARY KEY, federal_code TEXT NOT NULL, name TEXT NOT NULL,
                email TEXT NOT NULL, phone TEXT NOT NULL, contact_first_name TEXT NOT NULL,
                contact_last_name TEXT NOT NULL, contact_phone TEXT NOT NULL, contact_email TEXT,
                organization TEXT NOT NULL, recovery_email TEXT NOT NULL, password_hash TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE events (
                id INTEGER PRIMARY KEY, name TEXT NOT NULL, date TEXT NOT NULL,
                location TEXT NOT NULL, organizer TEXT, registration_deadline TEXT, type TEXT,
                description TEXT, notes TEXT, poster_file TEXT, info_file TEXT,
                published INTEGER NOT NULL, closed INTEGER NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE athletes (
                id INTEGER PRIMARY KEY, club_id INTEGER NOT NULL, last_name TEXT NOT NULL,
                first_name TEXT NOT NULL, gender TEXT NOT NULL, date_of_birth TEXT NOT NULL,
                weight_kg REAL NOT NULL, belt TEXT NOT NULL, membership_number TEXT, notes TEXT
            )'
        );
        $this->database->exec(
            'CREATE TABLE entries (
                id INTEGER PRIMARY KEY, event_id INTEGER NOT NULL, club_id INTEGER NOT NULL,
                athlete_id INTEGER NOT NULL, snapshot_last_name TEXT, snapshot_first_name TEXT,
                snapshot_gender TEXT, snapshot_date_of_birth TEXT, snapshot_weight_kg REAL,
                snapshot_belt TEXT, snapshot_membership_number TEXT, snapshot_program TEXT,
                snapshot_weight_category TEXT, snapshot_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $this->database->exec(
            "INSERT INTO clubs VALUES (
                201, 'SYN-201', 'Synthetic Club', 'club@example.test', '', 'Synthetic',
                'Contact', '', NULL, 'SYNTHETIC', 'recovery@example.test', 'synthetic-hash'
            )"
        );
        $this->database->exec(
            "INSERT INTO athletes VALUES (
                301, 201, 'Original', 'Athlete', 'M', '2014-01-01', 38, 'white', 'SYN-301', NULL
            )"
        );
        $event = $this->database->prepare(
            'INSERT INTO events VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $event->execute([
            101, '2026 Event', '2026-07-01', 'Synthetic Venue', 'Synthetic Organizer',
            '2026-06-30', 'only_competitive', '', '', null, null, 1, 0,
        ]);
        $event->execute([
            102, '2027 Event', '2027-07-01', 'Synthetic Venue', 'Synthetic Organizer',
            '2027-06-30', 'only_competitive', '', '', null, null, 1, 0,
        ]);
        $this->database->exec(
            'INSERT INTO entries (id, event_id, club_id, athlete_id) VALUES
             (1, 101, 201, 301), (2, 102, 201, 301)'
        );
    }
}
