<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\EventController;
use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Model\Database;
use App\Model\EntryRegistrationRepository;
use App\Model\EntryRegistrationResult;
use App\Model\Event;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class EventLifecycleTest extends TestCase
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
        $this->createSchemaAndActors();
        $this->databaseConnection->setValue(null, $this->database);

        $this->startCleanSession();
        Localization::setLocale('it');
        $this->view = new View(dirname(__DIR__) . '/views');
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
        $this->destroySession();
    }

    public function testPublicIdLookupDoesNotReturnAnUnpublishedEvent(): void
    {
        $this->insertEvent(published: false, description: 'UNPUBLISHED-EVENT-DATA');

        self::assertNull(Event::findPublishedById(101));

        $request = new Request('GET', '/event_details.php?event=101', ['event' => '101']);
        $response = (new EventController($this->view, $request))->show($request);

        self::assertSame(302, $response->status());
        self::assertStringNotContainsString('UNPUBLISHED-EVENT-DATA', $response->content());
    }

    /**
     * @return iterable<string, array{bool, bool, string, string|null, bool}>
     */
    public static function registrationLifecycleCases(): iterable
    {
        yield 'deadline equal and event today' => [true, false, '2026-06-28', '2026-06-28', true];
        yield 'unpublished' => [false, false, '2026-06-29', '2026-06-28', false];
        yield 'closed' => [true, true, '2026-06-29', '2026-06-28', false];
        yield 'deadline past' => [true, false, '2026-06-29', '2026-06-27', false];
        yield 'event past without deadline' => [true, false, '2026-06-27', null, false];
    }

    #[DataProvider('registrationLifecycleCases')]
    public function testRegistrationEnforcesEventLifecycleAtReadAndWriteBoundaries(
        bool $published,
        bool $closed,
        string $eventDate,
        ?string $deadline,
        bool $eligible
    ): void {
        $this->insertEvent($published, $closed, $eventDate, $deadline);

        $event = Event::findRegistrationEligibleById(101, '2026-06-28');
        $result = (new EntryRegistrationRepository($this->database))->register(
            101,
            201,
            301,
            '2026-06-28'
        );

        if ($eligible) {
            self::assertInstanceOf(Event::class, $event);
            self::assertSame(EntryRegistrationResult::Registered, $result);
            self::assertSame(1, $this->entryCount());

            return;
        }

        self::assertNull($event);
        self::assertSame(EntryRegistrationResult::AthleteRejected, $result);
        self::assertSame(0, $this->entryCount());
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function clubEntryQueries(): iterable
    {
        yield 'no requested club' => [['event' => '101']];
        yield 'forged foreign club' => [['event' => '101', 'club' => '202']];
    }

    /** @param array<string, string> $query */
    #[DataProvider('clubEntryQueries')]
    public function testClubEntryDetailsAreAlwaysScopedToTheSessionClub(array $query): void
    {
        $this->seedEntriesForTwoClubs();
        Session::set('club_id', 201);

        $response = $this->dispatchEntries($query);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('OwnFamily', $response->content());
        self::assertStringNotContainsString('ForeignFamily', $response->content());
    }

    public function testAdminCanSelectAnotherClubsEntryDetails(): void
    {
        $this->seedEntriesForTwoClubs();
        Session::set('is_admin', true);
        $response = $this->dispatchEntries([
            'event' => '101',
            'club' => '202',
        ]);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('ForeignFamily', $response->content());
        self::assertStringNotContainsString('OwnFamily', $response->content());
    }

    public function testAnonymousCanonicalEntryRouteRedirectsWithoutPersonalData(): void
    {
        $this->seedEntriesForTwoClubs();

        $response = $this->dispatchEntries(['event' => '101', 'club' => '202']);

        self::assertSame(302, $response->status());
        self::assertStringNotContainsString('OwnFamily', $response->content());
        self::assertStringNotContainsString('ForeignFamily', $response->content());
    }

    private function createSchemaAndActors(): void
    {
        $this->database->exec(
            'CREATE TABLE clubs (
                id INTEGER PRIMARY KEY,
                federal_code TEXT NOT NULL,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                phone TEXT NOT NULL,
                contact_first_name TEXT NOT NULL,
                contact_last_name TEXT NOT NULL,
                contact_phone TEXT NOT NULL,
                contact_email TEXT,
                organization TEXT NOT NULL,
                recovery_email TEXT NOT NULL,
                password_hash TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE events (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                date TEXT NOT NULL,
                location TEXT,
                organizer TEXT,
                registration_deadline TEXT,
                type TEXT,
                description TEXT,
                notes TEXT,
                poster_file TEXT,
                info_file TEXT,
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
                date_of_birth TEXT NOT NULL,
                weight_kg REAL NOT NULL,
                weight_category TEXT,
                belt TEXT,
                program TEXT NOT NULL,
                membership_number TEXT,
                notes TEXT
            )'
        );
        $this->database->exec(
            'CREATE TABLE entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                club_id INTEGER NOT NULL,
                athlete_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (event_id, club_id, athlete_id)
            )'
        );

        $club = $this->database->prepare(
            'INSERT INTO clubs (
                id, federal_code, name, email, phone, contact_first_name,
                contact_last_name, contact_phone, contact_email, organization,
                recovery_email, password_hash
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $club->execute([
            201,
            'OWN-201',
            'Own Club',
            'own@example.test',
            '',
            'Own',
            'Contact',
            '',
            null,
            'TEST',
            'own-recovery@example.test',
            'synthetic-hash',
        ]);
        $club->execute([
            202,
            'FOREIGN-202',
            'Foreign Club',
            'foreign@example.test',
            '',
            'Foreign',
            'Contact',
            '',
            null,
            'TEST',
            'foreign-recovery@example.test',
            'synthetic-hash',
        ]);

        $athlete = $this->database->prepare(
            'INSERT INTO athletes (
                id, club_id, last_name, first_name, gender, date_of_birth,
                weight_kg, weight_category, belt, program, membership_number, notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $athlete->execute([
            301,
            201,
            'OwnFamily',
            'OwnGiven',
            'M',
            '2012-01-01',
            40.0,
            '-40',
            'white',
            'competitive',
            'OWN-MEMBER',
            null,
        ]);
        $athlete->execute([
            302,
            202,
            'ForeignFamily',
            'ForeignGiven',
            'F',
            '2012-01-01',
            40.0,
            '-40',
            'white',
            'competitive',
            'FOREIGN-MEMBER',
            null,
        ]);
    }

    private function insertEvent(
        bool $published = true,
        bool $closed = false,
        string $date = '2026-06-29',
        ?string $deadline = '2026-06-28',
        ?string $description = null
    ): void {
        $statement = $this->database->prepare(
            'INSERT INTO events (
                id, name, date, location, organizer, registration_deadline,
                type, description, notes, poster_file, info_file, published, closed
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            101,
            'Synthetic Event',
            $date,
            'Synthetic Venue',
            'Synthetic Organizer',
            $deadline,
            'only_competitive',
            $description,
            null,
            'uploads/synthetic-poster.pdf',
            null,
            $published ? 1 : 0,
            $closed ? 1 : 0,
        ]);
    }

    private function seedEntriesForTwoClubs(): void
    {
        $this->insertEvent();
        $statement = $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        );
        $statement->execute([101, 201, 301]);
        $statement->execute([101, 202, 302]);
    }

    private function entryCount(): int
    {
        return (int) $this->database->query('SELECT COUNT(*) FROM entries')->fetchColumn();
    }

    /** @param array<string, string> $query */
    private function dispatchEntries(array $query): Response
    {
        $application = new Application(dirname(__DIR__));
        (require dirname(__DIR__) . '/routes/web.php')($application->router());

        return $application->handle(new Request('GET', '/event_entries.php', $query));
    }

    private function startCleanSession(): void
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
