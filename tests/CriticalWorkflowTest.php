<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Application;
use App\Core\Request;
use App\Core\Session;
use App\Localization;
use App\Model\ClubDataRightsDeclaration;
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
        $this->setEnvironment('APP_ENV', 'local');
        $this->setEnvironment('APP_DEBUG', 'true');
        $this->setEnvironment('APP_TEST_RESET_LINKS', 'true');
        $this->setEnvironment('APP_URL', 'https://club.example.test');
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
        foreach (['/privacy', '/events', '/clubs/register', '/clubs/login'] as $path) {
            self::assertSame(200, $this->request('GET', $path)->status(), $path);
        }

        $accountPassword = 'OriginalPassword123!';
        $updatedPassword = 'UpdatedPassword123!';
        $registration = $this->request('POST', '/clubs/register', [], [
            'csrf_token' => csrf_token(),
            'name' => 'Synthetic Club One',
            'federal_code' => 'SYN001',
            'email' => 'Club.One@Example.Test',
            'phone' => '0000000000',
            'address_line' => 'Via Roma 1',
            'postal_code' => '08100',
            'province' => 'Provincia di Nuoro',
            'city' => 'Nuoro',
            'contact_first_name' => 'Synthetic',
            'contact_last_name' => 'Contact',
            'affiliation' => ['FIJLKAM'],
            'password' => $accountPassword,
            'password2' => $accountPassword,
            'athlete_data_rights_declaration' => '1',
        ]);

        self::assertSame(200, $registration->status());
        self::assertStringContainsString(e(__('club.register.confirmation_sent')), $registration->content());
        self::assertSame(0, (int) $this->database->query('SELECT COUNT(*) FROM clubs')->fetchColumn());
        self::assertMatchesRegularExpression(
            '#/clubs/confirm-registration\?token=([a-f0-9]{64})#',
            $registration->content()
        );
        preg_match('#/clubs/confirm-registration\?token=([a-f0-9]{64})#', $registration->content(), $match);
        self::assertSame(200, $this->request(
            'GET',
            '/clubs/confirm-registration',
            ['token' => $match[1]]
        )->status());
        $clubId = (int) $this->database->query(
            "SELECT id FROM clubs WHERE email = 'club.one@example.test'"
        )->fetchColumn();
        self::assertGreaterThan(0, $clubId);
        $declaration = $this->database->query(
            'SELECT club_id, declared_by_club_id, declaration_version, declared_at '
            . 'FROM club_data_rights_declarations'
        )->fetch();
        self::assertSame($clubId, (int) $declaration['club_id']);
        self::assertSame($clubId, (int) $declaration['declared_by_club_id']);
        self::assertSame(ClubDataRightsDeclaration::VERSION, $declaration['declaration_version']);
        self::assertNotSame('', $declaration['declared_at']);

        $rawToken = 'synthetic-reset-token-that-never-leaves-this-test';
        $token = $this->database->prepare(
            'INSERT INTO password_reset_tokens (club_id, token_hash, expires_at, used) VALUES (?, ?, ?, 0)'
        );
        $token->execute([$clubId, hash('sha256', $rawToken), '2099-01-01 00:00:00']);
        self::assertSame(200, $this->request(
            'GET',
            '/clubs/reset-password',
            ['token' => $rawToken]
        )->status());
        $reset = $this->request('POST', '/clubs/reset-password', [], [
            'csrf_token' => csrf_token(),
            'token' => $rawToken,
            'password' => $updatedPassword,
            'password2' => $updatedPassword,
        ]);

        self::assertSame(302, $reset->status());
        self::assertSame('/clubs/login', $reset->headers()['Location']);
        self::assertSame(1, (int) $this->database->query(
            'SELECT used FROM password_reset_tokens'
        )->fetchColumn());
        self::assertTrue(password_verify(
            $updatedPassword,
            (string) $this->database->query('SELECT password_hash FROM clubs')->fetchColumn()
        ));

        $login = $this->request('POST', '/clubs/login', [], [
            'csrf_token' => csrf_token(),
            'email' => 'CLUB.ONE@EXAMPLE.TEST',
            'password' => $updatedPassword,
        ], ['REMOTE_ADDR' => '192.0.2.10']);
        self::assertSame(302, $login->status());
        self::assertSame($clubId, Session::get('club_id'));

        $createAthlete = $this->request('POST', '/clubs/area', ['view' => 'add'], [
            'csrf_token' => csrf_token(),
            'athlete_id' => '',
            'last_name' => 'VisibleOwn',
            'first_name' => 'Athlete',
            'gender' => 'M',
            'birth_date' => '2012-04-05',
            'weight_kg' => '42,5',
            'belt' => 'green',
            'membership_number' => 'OWN-001',
            'notes' => 'synthetic',
        ]);
        self::assertSame(302, $createAthlete->status());
        $athleteId = (int) $this->database->query(
            "SELECT id FROM athletes WHERE membership_number = 'OWN-001'"
        )->fetchColumn();

        $updateAthlete = $this->request('POST', '/clubs/area', ['view' => 'add'], [
            'csrf_token' => csrf_token(),
            'athlete_id' => (string) $athleteId,
            'last_name' => 'VisibleOwnUpdated',
            'first_name' => 'Athlete',
            'gender' => 'M',
            'birth_date' => '2012-04-05',
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
            '/clubs/area',
            ['view' => 'list']
        )->status());
        self::assertSame(200, $this->request(
            'GET',
            '/clubs/area',
            ['view' => 'add']
        )->status());

        $this->resetSession();
        $adminLogin = $this->request('POST', '/admin/login', [], [
            'csrf_token' => csrf_token(),
            'user' => 'synthetic-admin',
            'pass' => 'AdminPassword123!',
        ], ['REMOTE_ADDR' => '192.0.2.20']);
        self::assertSame(302, $adminLogin->status());
        self::assertTrue((bool) Session::get('is_admin'));

        $eventDate = '2098-07-01';
        $createEvent = $this->request('POST', '/admin/events/add', [], [
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

        $updateEvent = $this->request('POST', '/admin/events/add', [], [
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
                ['/admin/events', []],
                ['/admin/events/add', ['event_id' => (string) $eventId]],
                ['/admin/clubs', []],
                ['/admin/clubs/edit', ['id' => (string) $clubId]],
            ] as [$path, $query]
        ) {
            self::assertSame(200, $this->request('GET', $path, $query)->status(), $path);
        }

        $foreignClubId = $this->insertForeignClubAndAthlete();
        $foreignAthleteId = (int) $this->database->query(
            "SELECT id FROM athletes WHERE membership_number = 'FOREIGN-001'"
        )->fetchColumn();

        $this->resetSession();
        $clubLogin = $this->request('POST', '/clubs/login', [], [
            'csrf_token' => csrf_token(),
            'email' => 'club.one@example.test',
            'password' => $updatedPassword,
        ], ['REMOTE_ADDR' => '192.0.2.30']);
        self::assertSame(302, $clubLogin->status());

        $register = $this->request('POST', '/events/register', ['event' => (string) $eventId], [
            'csrf_token' => csrf_token(),
            'athletes' => [(string) $athleteId, (string) $foreignAthleteId],
        ]);
        self::assertSame(302, $register->status());
        self::assertSame(1, (int) $this->database->query(
            'SELECT COUNT(*) FROM entries WHERE club_id = ' . $clubId
        )->fetchColumn());
        $feedback = $this->request('GET', '/events/register', ['event' => (string) $eventId]);
        self::assertStringContainsString(__('events.registration_added', ['count' => '1']), $feedback->content());
        self::assertStringContainsString(__('events.registration_rejected', ['count' => '1']), $feedback->content());

        $foreignEntry = $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        );
        $foreignEntry->execute([$eventId, $foreignClubId, $foreignAthleteId]);

        $clubEntries = $this->request('GET', '/events/entries', [
            'event' => (string) $eventId,
            'club' => (string) $foreignClubId,
        ]);
        self::assertSame(200, $clubEntries->status());
        self::assertStringContainsString('Club', $clubEntries->content());

        $this->resetSession();
        Session::set('is_admin', true);
        $adminEntries = $this->request('GET', '/events/entries', [
            'event' => (string) $eventId,
            'club' => (string) $foreignClubId,
        ]);
        self::assertSame(200, $adminEntries->status());
        self::assertStringContainsString(__('events.entries_subscribed'), $adminEntries->content());

        $this->resetSession();
        Session::set('club_id', $clubId);
        $deleteAthlete = $this->request('POST', '/clubs/delete-athlete', [], [
            'csrf_token' => csrf_token(),
            'athlete_id' => (string) $athleteId,
        ]);
        self::assertSame(302, $deleteAthlete->status());
        self::assertSame(0, (int) $this->database->query(
            'SELECT COUNT(*) FROM athletes WHERE id = ' . $athleteId
        )->fetchColumn());

        $this->resetSession();
        Session::set('is_admin', true);
        $deleteEvent = $this->request('POST', '/admin/events/delete', [], [
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
              city, province, affiliation, password_hash)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $club->execute([
            'SYN002',
            'Synthetic Club Two',
            'club.two@example.test',
            '',
            'Foreign',
            'Contact',
            'Nuoro',
            'Provincia di Nuoro',
            'FIJLKAM',
            password_hash('ForeignPassword123!', PASSWORD_DEFAULT),
        ]);
        $clubId = (int) $this->database->lastInsertId();
        $athlete = $this->database->prepare(
            'INSERT INTO athletes
             (club_id, last_name, first_name, gender, birth_date, weight_kg,
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
                normalized_email TEXT GENERATED ALWAYS AS (LOWER(TRIM(email))) STORED UNIQUE,
                phone TEXT NOT NULL DEFAULT \'\',
                address_line TEXT,
                postal_code TEXT,
                city TEXT NOT NULL DEFAULT \'\',
                province TEXT NOT NULL DEFAULT \'\',
                contact_first_name TEXT NOT NULL DEFAULT \'\',
                contact_last_name TEXT NOT NULL DEFAULT \'\',
                affiliation TEXT NOT NULL DEFAULT \'\',
                password_hash TEXT NOT NULL
            );
            CREATE TABLE club_data_rights_declarations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                club_id INTEGER NOT NULL,
                declared_by_club_id INTEGER NOT NULL,
                declaration_version TEXT NOT NULL,
                declared_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE athletes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                club_id INTEGER NOT NULL,
                last_name TEXT NOT NULL,
                first_name TEXT NOT NULL,
                gender TEXT NOT NULL,
                birth_date TEXT NOT NULL,
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
                max_participants INTEGER,
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
                snapshot_birth_date TEXT,
                snapshot_weight_kg REAL,
                snapshot_belt TEXT,
                snapshot_membership_number TEXT,
                snapshot_program TEXT,
                snapshot_weight_category TEXT,
                snapshot_at TEXT,
                UNIQUE (event_id, club_id, athlete_id)
            );
            CREATE TABLE event_registration_exceptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                club_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (event_id, club_id)
            );
            CREATE TABLE password_reset_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                club_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at TEXT NOT NULL,
                used INTEGER NOT NULL DEFAULT 0
            );
            CREATE TABLE club_registration_confirmations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                token_hash TEXT NOT NULL UNIQUE,
                registration_payload TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                confirmed_at TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
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
