<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\AdminController;
use App\Controller\EventController;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Model\Database;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class EventEntriesCsvWorkflowTest extends TestCase
{
    private ReflectionProperty $databaseConnection;
    private ?PDO $originalConnection;
    private PDO $database;
    private View $view;

    protected function setUp(): void
    {
        $this->databaseConnection = new ReflectionProperty(Database::class, 'pdo');
        $connection = $this->databaseConnection->getValue();
        self::assertTrue($connection === null || $connection instanceof PDO);
        $this->originalConnection = $connection;

        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSchema();
        $this->seedData();
        $this->databaseConnection->setValue(null, $this->database);
        $this->view = new View(dirname(__DIR__) . '/views');
        $this->resetSession();
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
        $this->destroySession();
    }

    public function testAdministratorCanExportOneEventsEntriesAsCsv(): void
    {
        Session::set('is_admin', true);
        $request = new Request('GET', '/admin/events/export', ['event_id' => '701']);

        $response = (new AdminController($this->view, $request))->exportEventEntries($request);

        self::assertSame(200, $response->status());
        self::assertSame('text/csv; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertSame(
            'attachment; filename="event-entries-20260701-synthetic-event.csv"',
            $response->headers()['Content-Disposition']
        );
        self::assertSame('private, no-store, max-age=0', $response->headers()['Cache-Control']);
        self::assertStringStartsWith("\xEF\xBB\xBF", $response->content());

        $rows = $this->parseCsv($response->content());
        self::assertSame([
            'club_name',
            'federal_code',
            'last_name',
            'first_name',
            'gender',
            'birth_date',
            'weight_kg',
            'belt',
            'membership_number',
            'type',
            'weight_category',
        ], $rows[0]);
        self::assertSame([
            "'=Formula Club",
            'SYN-201',
            'Snapshot',
            'Athlete',
            'M',
            '2012-04-05',
            '42.5',
            'green',
            'MEM-001',
            'Pre-competitive',
            "'-42 kg",
        ], $rows[1]);
        self::assertCount(2, $rows);
    }

    public function testOpenEventExportDoesNotRequireClosedEventSnapshotColumns(): void
    {
        $this->database->exec(
            'UPDATE events SET closed = 0 WHERE id = 701;
             ALTER TABLE entries DROP COLUMN snapshot_birth_date'
        );
        Session::set('is_admin', true);
        $request = new Request('GET', '/admin/events/export', ['event_id' => '701']);

        $response = (new AdminController($this->view, $request))->exportEventEntries($request);

        self::assertSame(200, $response->status());
        $rows = $this->parseCsv($response->content());
        self::assertSame([
            "'=Formula Club",
            'SYN-201',
            'Live',
            'Athlete',
            'F',
            '2013-05-06',
            '39',
            'yellow',
            'LIVE-001',
            'competitive',
            "'-40 kg",
        ], $rows[1]);
    }

    public function testClubCanExportOnlyItsClosedEventEntriesForTheSelectedWeight(): void
    {
        $this->database->exec(
            "INSERT INTO clubs (id, federal_code, name) VALUES (202, 'SYN-202', 'Foreign Club');
             INSERT INTO athletes
                (id, last_name, first_name, gender, birth_date, weight_kg, belt, membership_number)
             VALUES (302, 'Foreign', 'Athlete', 'F', '2012-06-07', 42, 'blue', 'FOREIGN-001');
             INSERT INTO entries
                (id, event_id, club_id, athlete_id, snapshot_last_name, snapshot_first_name, snapshot_gender,
                 snapshot_weight_kg, snapshot_belt, snapshot_membership_number, snapshot_birth_date,
                 snapshot_program, snapshot_weight_category)
             VALUES (802, 701, 202, 302, 'Foreign', 'Athlete', 'F', 42, 'blue', 'FOREIGN-001',
                     '2012-06-07', 'competitive', '-42 kg')"
        );
        Session::set('club_id', 201);
        $request = new Request('GET', '/events/entries/export', [
            'event' => '701',
            'weight_category' => '-42 kg',
        ]);

        $response = (new EventController($this->view, $request))->exportClubEntries($request);

        self::assertSame(200, $response->status());
        self::assertSame(
            'attachment; filename="club-athletes-20260701-synthetic-event-42-kg.csv"',
            $response->headers()['Content-Disposition']
        );
        $rows = $this->parseCsv($response->content());
        self::assertCount(2, $rows);
        self::assertSame('Snapshot', $rows[1][2]);
        self::assertSame("'-42 kg", $rows[1][10]);
        self::assertStringNotContainsString('Foreign', $response->content());
    }

    public function testClubEntryExportIsUnavailableUntilTheEventCloses(): void
    {
        $this->database->exec('UPDATE events SET closed = 0 WHERE id = 701');
        Session::set('club_id', 201);
        $request = new Request('GET', '/events/entries/export', ['event' => '701']);

        $response = (new EventController($this->view, $request))->exportClubEntries($request);

        self::assertSame(302, $response->status());
        self::assertSame('/events/entries?event=701', $response->headers()['Location']);
    }

    public function testClubEntryExportRedirectsAnonymousUsersToLogin(): void
    {
        $request = new Request('GET', '/events/entries/export', ['event' => '701']);

        $response = (new EventController($this->view, $request))->exportClubEntries($request);

        self::assertSame(302, $response->status());
        self::assertSame('/clubs/login', $response->headers()['Location']);
    }

    public function testExportRedirectsAnonymousAdministratorsToLogin(): void
    {
        $request = new Request('GET', '/admin/events/export', ['event_id' => '701']);

        $response = (new AdminController($this->view, $request))->exportEventEntries($request);

        self::assertSame(302, $response->status());
        self::assertSame('/admin/login', $response->headers()['Location']);
    }

    /** @return list<list<string|null>> */
    private function parseCsv(string $csv): array
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    private function createSchema(): void
    {
        $this->database->exec(
            'CREATE TABLE clubs (
                id INTEGER PRIMARY KEY,
                federal_code TEXT NOT NULL,
                name TEXT NOT NULL
            );
            CREATE TABLE athletes (
                id INTEGER PRIMARY KEY,
                last_name TEXT NOT NULL,
                first_name TEXT NOT NULL,
                gender TEXT NOT NULL,
                birth_date TEXT NOT NULL,
                weight_kg REAL NOT NULL,
                belt TEXT NOT NULL,
                membership_number TEXT
            );
            CREATE TABLE events (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                date TEXT NOT NULL,
                location TEXT NOT NULL,
                organizer TEXT NOT NULL DEFAULT \'\',
                registration_deadline TEXT,
                type TEXT NOT NULL,
                description TEXT,
                notes TEXT,
                max_participants INTEGER,
                poster_file TEXT,
                info_file TEXT,
                published INTEGER NOT NULL DEFAULT 0,
                closed INTEGER NOT NULL DEFAULT 0
            );
            CREATE TABLE event_registration_exceptions (
                event_id INTEGER NOT NULL,
                club_id INTEGER NOT NULL
            );
            CREATE TABLE entries (
                id INTEGER PRIMARY KEY,
                event_id INTEGER NOT NULL,
                club_id INTEGER NOT NULL,
                athlete_id INTEGER NOT NULL,
                snapshot_last_name TEXT,
                snapshot_first_name TEXT,
                snapshot_gender TEXT,
                snapshot_weight_kg REAL,
                snapshot_belt TEXT,
                snapshot_membership_number TEXT,
                snapshot_birth_date TEXT,
                snapshot_program TEXT,
                snapshot_weight_category TEXT
            )'
        );
    }

    private function seedData(): void
    {
        $this->database->exec(
            "INSERT INTO clubs (id, federal_code, name) VALUES (201, 'SYN-201', '=Formula Club');
             INSERT INTO athletes
                (id, last_name, first_name, gender, birth_date, weight_kg, belt, membership_number)
             VALUES (301, 'Live', 'Athlete', 'F', '2013-05-06', 39, 'yellow', 'LIVE-001');
             INSERT INTO events
                (id, name, date, location, type, published, closed)
             VALUES (701, 'Synthetic event', '2026-07-01', 'Test city', 'only_competitive', 1, 1);
             INSERT INTO entries
                (id, event_id, club_id, athlete_id, snapshot_last_name, snapshot_first_name, snapshot_gender,
                 snapshot_weight_kg, snapshot_belt, snapshot_membership_number, snapshot_birth_date,
                 snapshot_program, snapshot_weight_category)
             VALUES (801, 701, 201, 301, 'Snapshot', 'Athlete', 'M', 42.5, 'green', 'MEM-001',
                     '2012-04-05', 'Pre-competitive', '-42 kg')"
        );
    }

    private function resetSession(): void
    {
        $this->destroySession();
        Session::start();
    }

    private function destroySession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            Session::destroy();
        }
        $_SESSION = [];
        session_id('');
    }
}
