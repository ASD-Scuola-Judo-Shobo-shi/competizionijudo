<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Application;
use App\Core\Request;
use App\Core\Session;
use App\Localization;
use App\Model\Database;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\Support\CriticalWorkflowPdo;

final class CriticalWorkflowTest extends TestCase
{
    private ReflectionProperty $databaseConnection;
    private ?PDO $originalConnection;
    private CriticalWorkflowPdo $database;
    private Application $application;

    /** @var array<string, mixed> */
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        $this->databaseConnection = new ReflectionProperty(Database::class, 'pdo');
        $connection = $this->databaseConnection->getValue();
        self::assertTrue($connection === null || $connection instanceof PDO);
        $this->originalConnection = $connection;

        $this->database = new CriticalWorkflowPdo('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->database->sqliteCreateFunction(
            'LAST_INSERT_ID',
            fn(): int => (int) $this->database->lastInsertId()
        );
        $this->createSchema();
        $this->databaseConnection->setValue(null, $this->database);

        $this->setEnvironment('ADMIN_USER', 'synthetic-admin');
        $this->setEnvironment('ADMIN_PASS_HASH', password_hash('AdminPassword123!', PASSWORD_DEFAULT));
        Localization::setLocale('it');
        $this->resetSession();

        $this->application = new Application(dirname(__DIR__));
        (require dirname(__DIR__) . '/routes/web.php')($this->application->router());
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
        foreach ($this->originalEnvironment as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
        $this->destroySession();
    }

    public function testCriticalAccountAthleteEventRegistrationAndPrivacyWorkflow(): void
    {
        foreach (['/', '/privacy', '/events.php', '/club_register.php', '/club_login.php'] as $path) {
            self::assertSame(200, $this->request('GET', $path)->status(), $path);
        }

        $accountPassword = 'OriginalPassword123!';
        $updatedPassword = 'UpdatedPassword123!';
        $registration = $this->request('POST', '/club_register.php', [], [
            'csrf_token' => csrf_token(),
            'name' => 'Synthetic Club One',
            'federal_code' => 'SYN001',
            'email' => 'Club.One@Example.Test',
            'phone' => '0000000000',
            'contact' => 'Synthetic Contact',
            'password' => $accountPassword,
            'password2' => $accountPassword,
            'athlete_data_rights_declaration' => '1',
        ]);

        self::assertSame(200, $registration->status());
        self::assertStringContainsString(__('club.register.success_message'), $registration->content());
        $clubId = (int) $this->database->query(
            "SELECT id FROM clubs WHERE email = 'club.one@example.test'"
        )->fetchColumn();
        self::assertGreaterThan(0, $clubId);

        $rawToken = 'synthetic-reset-token-that-never-leaves-this-test';
        $token = $this->database->prepare(
            'INSERT INTO password_reset_tokens (club_id, token_hash, expires_at, used) VALUES (?, ?, ?, 0)'
        );
        $token->execute([$clubId, hash('sha256', $rawToken), '2099-01-01 00:00:00']);
        self::assertSame(200, $this->request(
            'GET',
            '/club_reset_password.php',
            ['token' => $rawToken]
        )->status());
        $reset = $this->request('POST', '/club_reset_password.php', [], [
            'csrf_token' => csrf_token(),
            'token' => $rawToken,
            'password' => $updatedPassword,
            'password2' => $updatedPassword,
        ]);

        self::assertSame(302, $reset->status());
        self::assertSame('/club_login.php', $reset->headers()['Location']);
        self::assertSame(1, (int) $this->database->query(
            'SELECT used FROM password_reset_tokens'
        )->fetchColumn());
        self::assertTrue(password_verify(
            $updatedPassword,
            (string) $this->database->query('SELECT password_hash FROM clubs')->fetchColumn()
        ));

        $login = $this->request('POST', '/club_login.php', [], [
            'csrf_token' => csrf_token(),
            'email' => 'CLUB.ONE@EXAMPLE.TEST',
            'password' => $updatedPassword,
        ], ['REMOTE_ADDR' => '192.0.2.10']);
        self::assertSame(302, $login->status());
        self::assertSame($clubId, Session::get('club_id'));

        $createAthlete = $this->request('POST', '/club_area.php', ['view' => 'add'], [
            'csrf_token' => csrf_token(),
            'athlete_id' => '',
            'last_name' => 'VisibleOwn',
            'first_name' => 'Athlete',
            'gender' => 'M',
            'date_of_birth' => '2012-04-05',
            'weight_kg' => '42,5',
            'belt' => 'green',
            'membership_number' => 'OWN-001',
            'notes' => 'synthetic',
        ]);
        self::assertSame(302, $createAthlete->status());
        $athleteId = (int) $this->database->query(
            "SELECT id FROM athletes WHERE membership_number = 'OWN-001'"
        )->fetchColumn();

        $updateAthlete = $this->request('POST', '/club_area.php', ['view' => 'add'], [
            'csrf_token' => csrf_token(),
            'athlete_id' => (string) $athleteId,
            'last_name' => 'VisibleOwnUpdated',
            'first_name' => 'Athlete',
            'gender' => 'M',
            'date_of_birth' => '2012-04-05',
            'weight_kg' => '43.0',
            'belt' => 'blue',
            'membership_number' => 'OWN-001',
            'notes' => 'synthetic update',
        ]);
        self::assertSame(302, $updateAthlete->status());
        self::assertSame('VisibleOwnUpdated', $this->database->query(
            'SELECT last_name FROM athletes WHERE id = ' . $athleteId
        )->fetchColumn());
        self::assertSame(200, $this->request(
            'GET',
            '/club_area.php',
            ['view' => 'list']
        )->status());
        self::assertSame(200, $this->request(
            'GET',
            '/club_area.php',
            ['view' => 'add']
        )->status());

        $this->resetSession();
        $adminLogin = $this->request('POST', '/admin_login.php', [], [
            'csrf_token' => csrf_token(),
            'user' => 'synthetic-admin',
            'pass' => 'AdminPassword123!',
        ], ['REMOTE_ADDR' => '192.0.2.20']);
        self::assertSame(302, $adminLogin->status());
        self::assertTrue((bool) Session::get('is_admin'));

        $eventDate = '2098-07-01';
        $createEvent = $this->request('POST', '/admin_add_event.php', [], [
            'csrf_token' => csrf_token(),
            'event_id' => '',
            'name' => 'Synthetic Event',
            'date' => $eventDate,
            'location' => 'Synthetic City',
            'organizer' => 'Synthetic Organizer',
            'registration_deadline' => '2098-06-30',
            'type' => 'only_competitive',
            'description' => 'Synthetic description',
            'notes' => '',
            'published' => '1',
            'closed' => '0',
        ]);
        self::assertSame(302, $createEvent->status());
        $eventId = (int) $this->database->query(
            "SELECT id FROM events WHERE name = 'Synthetic Event'"
        )->fetchColumn();

        $updateEvent = $this->request('POST', '/admin_add_event.php', [], [
            'csrf_token' => csrf_token(),
            'event_id' => (string) $eventId,
            'name' => 'Synthetic Event Updated',
            'date' => $eventDate,
            'location' => 'Synthetic City',
            'organizer' => 'Synthetic Organizer',
            'registration_deadline' => '2098-06-30',
            'type' => 'only_competitive',
            'description' => 'Synthetic description',
            'notes' => '',
            'published' => '1',
            'closed' => '0',
        ]);
        self::assertSame(302, $updateEvent->status());
        self::assertSame('Synthetic Event Updated', $this->database->query(
            'SELECT name FROM events WHERE id = ' . $eventId
        )->fetchColumn());
        foreach (
            [
                ['/admin_manage_events.php', []],
                ['/admin_add_event.php', ['event_id' => (string) $eventId]],
                ['/admin_manage_clubs.php', []],
                ['/admin_edit_club.php', ['id' => (string) $clubId]],
                ['/events.php', []],
                ['/event_details.php', ['event' => (string) $eventId]],
            ] as [$path, $query]
        ) {
            self::assertSame(200, $this->request('GET', $path, $query)->status(), $path);
        }

        $foreignClubId = $this->insertForeignClubAndAthlete();
        $foreignAthleteId = (int) $this->database->query(
            "SELECT id FROM athletes WHERE membership_number = 'FOREIGN-001'"
        )->fetchColumn();

        $this->resetSession();
        $clubLogin = $this->request('POST', '/club_login.php', [], [
            'csrf_token' => csrf_token(),
            'email' => 'club.one@example.test',
            'password' => $updatedPassword,
        ], ['REMOTE_ADDR' => '192.0.2.30']);
        self::assertSame(302, $clubLogin->status());

        $register = $this->request('POST', '/event_register.php', ['id' => (string) $eventId], [
            'csrf_token' => csrf_token(),
            'athletes' => [(string) $athleteId, (string) $foreignAthleteId],
        ]);
        self::assertSame(302, $register->status());
        self::assertSame(1, (int) $this->database->query(
            'SELECT COUNT(*) FROM entries WHERE club_id = ' . $clubId
        )->fetchColumn());
        $feedback = $this->request('GET', '/event_register.php', ['id' => (string) $eventId]);
        self::assertStringContainsString(__('events.registration_added', ['count' => '1']), $feedback->content());
        self::assertStringContainsString(__('events.registration_rejected', ['count' => '1']), $feedback->content());

        $foreignEntry = $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        );
        $foreignEntry->execute([$eventId, $foreignClubId, $foreignAthleteId]);

        $clubEntries = $this->request('GET', '/event_entries.php', [
            'event' => (string) $eventId,
            'club' => (string) $foreignClubId,
        ]);
        self::assertSame(200, $clubEntries->status());
        self::assertStringContainsString('VisibleOwnUpdated', $clubEntries->content());
        self::assertStringNotContainsString('HiddenForeign', $clubEntries->content());

        $this->resetSession();
        Session::set('is_admin', true);
        $adminEntries = $this->request('GET', '/event_entries.php', [
            'event' => (string) $eventId,
            'club' => (string) $foreignClubId,
        ]);
        self::assertSame(200, $adminEntries->status());
        self::assertStringContainsString('HiddenForeign', $adminEntries->content());
        self::assertStringNotContainsString('VisibleOwnUpdated', $adminEntries->content());

        $this->resetSession();
        Session::set('club_id', $clubId);
        $deleteAthlete = $this->request('POST', '/club_delete_athlete.php', [], [
            'csrf_token' => csrf_token(),
            'athlete_id' => (string) $athleteId,
        ]);
        self::assertSame(302, $deleteAthlete->status());
        self::assertSame(0, (int) $this->database->query(
            'SELECT COUNT(*) FROM athletes WHERE id = ' . $athleteId
        )->fetchColumn());

        $this->resetSession();
        Session::set('is_admin', true);
        $deleteEvent = $this->request('POST', '/admin_delete_event.php', [], [
            'csrf_token' => csrf_token(),
            'event_id' => (string) $eventId,
        ]);
        self::assertSame(302, $deleteEvent->status());
        self::assertSame(0, (int) $this->database->query(
            'SELECT COUNT(*) FROM events WHERE id = ' . $eventId
        )->fetchColumn());
    }

    /** @param array<string, mixed> $query @param array<string, mixed> $post @param array<string, mixed> $server */
    private function request(
        string $method,
        string $path,
        array $query = [],
        array $post = [],
        array $server = []
    ): \App\Core\Response {
        return $this->application->handle(new Request($method, $path, $query, $post, $server));
    }

    private function insertForeignClubAndAthlete(): int
    {
        $club = $this->database->prepare(
            'INSERT INTO clubs
             (federal_code, name, email, phone, contact_first_name, contact_last_name,
              contact_phone, contact_email, organization, recovery_email, password_hash)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $club->execute([
            'SYN002',
            'Synthetic Club Two',
            'club.two@example.test',
            '',
            'Foreign',
            'Contact',
            '',
            'club.two@example.test',
            'FIJLKAM',
            'club.two@example.test',
            password_hash('ForeignPassword123!', PASSWORD_DEFAULT),
        ]);
        $clubId = (int) $this->database->lastInsertId();
        $athlete = $this->database->prepare(
            'INSERT INTO athletes
             (club_id, last_name, first_name, gender, date_of_birth, weight_kg,
              belt, membership_number, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $athlete->execute([
            $clubId,
            'HiddenForeign',
            'Athlete',
            'F',
            '2012-05-06',
            40.0,
            'green',
            'FOREIGN-001',
            'synthetic',
        ]);

        return $clubId;
    }

    private function createSchema(): void
    {
        $this->database->exec(
            'CREATE TABLE clubs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                federal_code TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                phone TEXT NOT NULL DEFAULT \'\',
                contact_first_name TEXT NOT NULL DEFAULT \'\',
                contact_last_name TEXT NOT NULL DEFAULT \'\',
                contact_phone TEXT NOT NULL DEFAULT \'\',
                contact_email TEXT NOT NULL DEFAULT \'\',
                organization TEXT NOT NULL DEFAULT \'\',
                recovery_email TEXT NOT NULL DEFAULT \'\',
                password_hash TEXT NOT NULL
            );
            CREATE TABLE athletes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                club_id INTEGER NOT NULL,
                last_name TEXT NOT NULL,
                first_name TEXT NOT NULL,
                gender TEXT NOT NULL,
                date_of_birth TEXT NOT NULL,
                weight_kg REAL NOT NULL,
                belt TEXT NOT NULL,
                membership_number TEXT,
                notes TEXT
            );
            CREATE TABLE events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                date TEXT NOT NULL,
                location TEXT NOT NULL,
                organizer TEXT NOT NULL DEFAULT \'\',
                registration_deadline TEXT,
                type TEXT NOT NULL,
                description TEXT,
                notes TEXT,
                poster_file TEXT,
                info_file TEXT,
                published INTEGER NOT NULL DEFAULT 0,
                closed INTEGER NOT NULL DEFAULT 0
            );
            CREATE TABLE entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                club_id INTEGER NOT NULL,
                athlete_id INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                snapshot_last_name TEXT,
                snapshot_first_name TEXT,
                snapshot_gender TEXT,
                snapshot_date_of_birth TEXT,
                snapshot_weight_kg REAL,
                snapshot_belt TEXT,
                snapshot_membership_number TEXT,
                snapshot_program TEXT,
                snapshot_weight_category TEXT,
                snapshot_at TEXT,
                UNIQUE (event_id, club_id, athlete_id)
            );
            CREATE TABLE password_reset_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                club_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at TEXT NOT NULL,
                used INTEGER NOT NULL DEFAULT 0
            );
            CREATE TABLE authentication_throttles (
                throttle_key TEXT PRIMARY KEY,
                attempt_count INTEGER NOT NULL DEFAULT 0,
                window_started_at TEXT NOT NULL,
                blocked_until TEXT,
                updated_at TEXT NOT NULL
            )'
        );
    }

    private function setEnvironment(string $key, string $value): void
    {
        $this->originalEnvironment[$key] = $_ENV[$key] ?? null;
        $_ENV[$key] = $value;
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
