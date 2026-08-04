<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\AdminController;
use App\Controller\ClubAreaController;
use App\Controller\ClubController;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Model\Club;
use App\Model\ClubDataRightsDeclaration;
use App\Model\ClubTermsAcceptance;
use App\Model\Database;
use App\Service\ClubQuota;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\Support\FakeAuthenticationThrottle;

final class ClubApprovalControllerTest extends TestCase
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
        Localization::setLocale('en');
        $this->destroySession();
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
        $this->destroySession();
        $_GET = [];
    }

    public function testLoginRejectsPendingClubWithSpecificError(): void
    {
        $throttle = new FakeAuthenticationThrottle();
        $request = new Request('POST', '/clubs/login', [], [
            'csrf_token' => csrf_token(),
            'email' => 'pending@example.test',
            'password' => 'SyntheticPassword123!',
        ], ['REMOTE_ADDR' => '192.0.2.10']);

        $response = $this->clubController($request, $throttle)->login($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e(__('club.login.errors.not_approved')), $response->content());
        self::assertNull(Session::get('club_id'));
        self::assertCount(1, $throttle->recorded);
    }

    public function testLoginAcceptsApprovedClubAndClearsTheThrottle(): void
    {
        $throttle = new FakeAuthenticationThrottle();
        $request = new Request('POST', '/clubs/login', [], [
            'csrf_token' => csrf_token(),
            'email' => 'APPROVED@example.test',
            'password' => 'SyntheticPassword123!',
        ], ['REMOTE_ADDR' => '192.0.2.11']);

        $response = $this->clubController($request, $throttle)->login($request);

        self::assertSame(302, $response->status());
        self::assertSame('/clubs/area?view=list', $response->headers()['Location']);
        self::assertSame(201, Session::get('club_id'));
        self::assertSame([], $throttle->attempts);
    }

    public function testApproveIsIdempotentAndSetsTheApprovalTimestamp(): void
    {
        $pending = Club::findById(202);
        self::assertNotNull($pending);
        self::assertFalse($pending->isApproved());

        self::assertTrue(Club::approve(202));
        self::assertFalse(Club::approve(202));

        $approved = Club::findById(202);
        self::assertNotNull($approved);
        self::assertTrue($approved->isApproved());
        self::assertNotSame('', (string) $approved->approved_at);
    }

    public function testAdminApproveActionApprovesAndRedirectsBackToTheRow(): void
    {
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));
        $request = new Request('POST', '/admin/clubs/approve', [], [
            'csrf_token' => csrf_token(),
            'club_id' => '202',
            'page' => '2',
            'sort' => 'name',
            'direction' => 'asc',
        ]);

        $response = (new AdminController($this->view, $request))->approveClub($request);

        self::assertSame(303, $response->status());
        self::assertSame(
            '/admin/clubs?page=2&sort=name&direction=asc#club-row-202',
            $response->headers()['Location']
        );
        $club = Club::findById(202);
        self::assertNotNull($club);
        self::assertTrue($club->isApproved());
        self::assertSame(['type' => 'success'], [
            'type' => Session::pullFlash('admin_club_inline_feedback')['type'] ?? null,
        ]);
    }

    public function testAdminApproveActionUnknownClubDoesNotWriteAndFlashesError(): void
    {
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));
        $request = new Request('POST', '/admin/clubs/approve', [], [
            'csrf_token' => csrf_token(),
            'club_id' => '999',
        ]);

        $response = (new AdminController($this->view, $request))->approveClub($request);

        self::assertSame(303, $response->status());
        self::assertSame('/admin/clubs?page=1#club-row-999', $response->headers()['Location']);
        $flash = Session::pullFlash('admin_club_inline_feedback');
        self::assertIsArray($flash);
        self::assertSame('error', $flash['type']);
        $pending = Club::findById(202);
        self::assertNotNull($pending);
        self::assertFalse($pending->isApproved());
    }

    public function testAdminApproveActionRequiresCsrf(): void
    {
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));
        $request = new Request('POST', '/admin/clubs/approve', [], [
            'csrf_token' => 'synthetic-invalid-csrf',
            'club_id' => '202',
        ]);

        $this->assertCsrfRejected(fn (): \App\Core\Response =>
            (new AdminController($this->view, $request))->approveClub($request));
    }

    public function testAdminApproveActionRedirectsAnonymousAdministrators(): void
    {
        $request = new Request('POST', '/admin/clubs/approve', [], [
            'csrf_token' => csrf_token(),
            'club_id' => '202',
        ]);

        $response = (new AdminController($this->view, $request))->approveClub($request);

        self::assertSame(302, $response->status());
        self::assertSame('/admin/login', $response->headers()['Location']);
        $pending = Club::findById(202);
        self::assertNotNull($pending);
        self::assertFalse($pending->isApproved());
    }

    public function testClubListShowsPendingBadgeUntilApproved(): void
    {
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));
        $request = new Request('GET', '/admin/clubs');

        $response = (new AdminController($this->view, $request))->manageClubs($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e(__('admin.clubs.status_pending')), $response->content());
        self::assertStringContainsString('status-badge--pending', $response->content());
        self::assertStringContainsString(
            e(base_url('/admin/clubs/approve')),
            $response->content()
        );
    }

    public function testClubListShowsApprovedBadgeWithoutApproveFormAfterApproval(): void
    {
        Club::approve(202);
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));
        $request = new Request('GET', '/admin/clubs');

        $response = (new AdminController($this->view, $request))->manageClubs($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e(__('admin.clubs.status_approved')), $response->content());
        self::assertStringContainsString('status-badge--approved', $response->content());
        self::assertStringNotContainsString('status-badge--pending', $response->content());
    }

    public function testAthleteAddIsBlockedByTheQuotaButEditsStillSucceed(): void
    {
        Session::authenticateClub(201, hash('sha256', 'test-club-credential'));
        $this->withAthleteLimit('1', function (): void {
            $request = new Request('POST', '/clubs/area', ['view' => 'add'], [
                'csrf_token' => csrf_token(),
                'athlete_id' => '',
                'last_name' => 'Quota',
                'first_name' => 'Blocked',
                'gender' => 'M',
                'birth_date' => '2012-04-05',
                'weight_kg' => '42,5',
                'belt' => 'green',
                'membership_number' => 'QUOTA-BLOCKED-001',
                'notes' => 'synthetic',
            ], ['REMOTE_ADDR' => '192.0.2.20']);

            $response = (new ClubAreaController($this->view, $request))->index($request);

            self::assertSame(200, $response->status());
            self::assertStringContainsString(
                e(__('errors.athlete_quota_exceeded', ['limit' => '1'])),
                $response->content()
            );
            self::assertSame(0, (int) $this->database->query(
                "SELECT COUNT(*) FROM athletes WHERE membership_number = 'QUOTA-BLOCKED-001'"
            )->fetchColumn());

            $updateRequest = new Request('POST', '/clubs/area', ['view' => 'add'], [
                'csrf_token' => csrf_token(),
                'athlete_id' => '301',
                'last_name' => 'QuotaEdit',
                'first_name' => 'Athlete',
                'gender' => 'M',
                'birth_date' => '2012-04-05',
                'weight_kg' => '43.0',
                'belt' => 'blue',
                'membership_number' => 'OWN-001',
                'notes' => 'synthetic update',
            ], ['REMOTE_ADDR' => '192.0.2.21']);

            $updateResponse = (new ClubAreaController($this->view, $updateRequest))->index($updateRequest);

            self::assertSame(302, $updateResponse->status());
            self::assertSame('/clubs/area?view=add', $updateResponse->headers()['Location']);
            self::assertSame('QuotaEdit', $this->database->query(
                'SELECT last_name FROM athletes WHERE id = 301'
            )->fetchColumn());
        });
    }

    public function testClubAreaShowsQuotaRemainingOnlyWhenTheLimitIsEnabled(): void
    {
        Session::authenticateClub(201, hash('sha256', 'test-club-credential'));
        $this->withAthleteLimit('3', function (): void {
            $request = new Request('GET', '/clubs/area', ['view' => 'list']);

            $response = (new ClubAreaController($this->view, $request))->index($request);

            self::assertSame(200, $response->status());
            self::assertStringContainsString(
                e(__('club.area.quota_athletes', [
                    'current' => (string) (3 - ClubQuota::remainingAthletes(201)),
                    'limit' => '3',
                ])),
                $response->content()
            );
        });
        $this->withAthleteLimit('0', function (): void {
            $request = new Request('GET', '/clubs/area', ['view' => 'list']);

            $response = (new ClubAreaController($this->view, $request))->index($request);

            self::assertSame(200, $response->status());
            self::assertStringNotContainsString(
                e(__('club.area.quota_athletes', ['current' => '0', 'limit' => '0'])),
                $response->content()
            );
        });
    }

    private function clubController(Request $request, FakeAuthenticationThrottle $throttle): ClubController
    {
        return new ClubController($this->view, $request, null, $throttle);
    }

    private function withAthleteLimit(?string $value, callable $callback): void
    {
        $original = $_ENV['CLUB_ATHLETE_LIMIT'] ?? null;
        if ($value === null) {
            unset($_ENV['CLUB_ATHLETE_LIMIT']);
        } else {
            $_ENV['CLUB_ATHLETE_LIMIT'] = $value;
        }

        try {
            $callback();
        } finally {
            if ($original === null) {
                unset($_ENV['CLUB_ATHLETE_LIMIT']);
            } else {
                $_ENV['CLUB_ATHLETE_LIMIT'] = $original;
            }
        }
    }

    private function assertCsrfRejected(callable $action): void
    {
        try {
            $action();
            self::fail('Expected CSRF validation to reject the request.');
        } catch (HttpException $exception) {
            self::assertSame(419, $exception->statusCode());
        }
    }

    private function seedData(): void
    {
        $club = $this->database->prepare(
            'INSERT INTO clubs
             (id, federal_code, name, email, phone, contact_first_name, contact_last_name,
              contact_phone, contact_email, affiliation, recovery_email, approved_at, password_hash)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $club->execute([
            201, 'SYN-201', 'Approved Club', 'approved@example.test', '', 'Own', 'Contact', '', '',
            'FIJLKAM', 'approved@example.test', '2026-01-01 00:00:00',
            password_hash('SyntheticPassword123!', PASSWORD_DEFAULT),
        ]);
        $club->execute([
            202, 'SYN-202', 'Pending Club', 'pending@example.test', '', 'Pending', 'Contact', '', '',
            'FIJLKAM', 'pending@example.test', null,
            password_hash('SyntheticPassword123!', PASSWORD_DEFAULT),
        ]);
        $this->database->prepare(
            'INSERT INTO club_data_rights_declarations '
            . '(club_id, declared_by_club_id, declaration_version) VALUES (?, ?, ?)'
        )->execute([201, 201, ClubDataRightsDeclaration::VERSION]);
        $this->database->prepare(
            'INSERT INTO club_terms_acceptances '
            . '(club_id, accepted_by_club_id, representative_name, accepted_account_email, '
            . 'terms_version, accepted_locale) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([201, 201, 'Own Contact', 'approved@example.test', ClubTermsAcceptance::VERSION, 'en']);
        $this->database->prepare(
            'INSERT INTO athletes
             (id, club_id, last_name, first_name, gender, birth_date, weight_kg, belt,
              membership_number, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            301, 201, 'Existing', 'Athlete', 'M', '2012-04-05', 42.5, 'green',
            'OWN-001', 'synthetic',
        ]);
    }

    private function createSchema(): void
    {
        $this->database->exec(
            'CREATE TABLE clubs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                federal_code TEXT NOT NULL,
                name TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                normalized_email TEXT GENERATED ALWAYS AS (LOWER(TRIM(email))) STORED UNIQUE,
                phone TEXT NOT NULL DEFAULT \'\',
                address_line TEXT,
                postal_code TEXT,
                city TEXT NOT NULL DEFAULT \'\',
                province TEXT NOT NULL DEFAULT \'\',
                contact_first_name TEXT NOT NULL,
                contact_last_name TEXT NOT NULL,
                contact_phone TEXT NOT NULL DEFAULT \'\',
                contact_email TEXT,
                affiliation TEXT NOT NULL DEFAULT \'\',
                recovery_email TEXT NOT NULL,
                approved_at TEXT,
                password_hash TEXT NOT NULL
            );
            CREATE TABLE athletes (
                id INTEGER PRIMARY KEY,
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
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                date TEXT NOT NULL,
                location TEXT NOT NULL,
                organizer TEXT NOT NULL,
                registration_deadline TEXT,
                type TEXT NOT NULL,
                description TEXT,
                notes TEXT,
                poster_file TEXT,
                info_file TEXT,
                published INTEGER NOT NULL,
                closed INTEGER NOT NULL,
                max_participants INTEGER
            );
            CREATE TABLE entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                club_id INTEGER NOT NULL,
                athlete_id INTEGER NOT NULL,
                registration_option_id INTEGER NOT NULL,
                registration_option_name TEXT NOT NULL,
                registration_fee_cents INTEGER NOT NULL,
                UNIQUE (event_id, club_id, athlete_id)
            );
            CREATE TABLE club_data_rights_declarations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                club_id INTEGER NOT NULL,
                declared_by_club_id INTEGER NOT NULL,
                declaration_version TEXT NOT NULL,
                declared_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (club_id, declaration_version)
            );
            CREATE TABLE club_terms_acceptances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                club_id INTEGER NOT NULL,
                accepted_by_club_id INTEGER NOT NULL,
                representative_name TEXT NOT NULL,
                accepted_account_email TEXT NOT NULL,
                terms_version TEXT NOT NULL,
                accepted_locale TEXT NOT NULL,
                accepted_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (club_id, terms_version)
            );'
        );
    }

    private function destroySession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            Session::destroy();
        }
        $_SESSION = [];
    }
}
