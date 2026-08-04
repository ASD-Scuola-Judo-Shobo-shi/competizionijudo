<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\AthleteMaintenanceController;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Model\Database;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class AthleteDuplicateMaintenanceControllerTest extends TestCase
{
    private ReflectionProperty $databaseConnection;
    private ?PDO $originalConnection;
    private PDO $database;
    private View $view;
    private bool $hadEnvironmentFlag;
    private mixed $originalEnvironmentFlag;
    private bool $hadServerFlag;
    private mixed $originalServerFlag;

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

        $this->hadEnvironmentFlag = array_key_exists('ATHLETE_DUPLICATE_MAINTENANCE', $_ENV);
        $this->originalEnvironmentFlag = $_ENV['ATHLETE_DUPLICATE_MAINTENANCE'] ?? null;
        $this->hadServerFlag = array_key_exists('ATHLETE_DUPLICATE_MAINTENANCE', $_SERVER);
        $this->originalServerFlag = $_SERVER['ATHLETE_DUPLICATE_MAINTENANCE'] ?? null;
        $this->setMaintenanceFlag('true');

        $this->destroySession();
        Session::start();
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));
        Localization::setLocale('en');
        $this->view = new View(dirname(__DIR__) . '/views');
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
        $this->restoreMaintenanceFlag();
        $this->destroySession();
    }

    public function testEnabledPageIsPrivateAndShowsTheProductionSafeguards(): void
    {
        $request = new Request('GET', '/admin/maintenance/athlete-duplicates');

        $response = (new AthleteMaintenanceController($this->view, $request))->duplicates($request);

        self::assertSame(200, $response->status());
        self::assertSame('private, no-store, max-age=0', $response->headers()['Cache-Control']);
        self::assertSame('noindex, nofollow', $response->headers()['X-Robots-Tag']);
        self::assertStringContainsString('Historical athlete duplicate cleanup', $response->content());
        self::assertStringContainsString('Synthetic Club — SYN-201', $response->content());
        self::assertStringContainsString(AthleteMaintenanceController::CONFIRMATION, $response->content());
        self::assertStringContainsString('Preview without changes', $response->content());
        self::assertStringContainsString('Duplicate cleanup', $response->content());
        self::assertSame(2, $this->athleteCount(201));
    }

    public function testPreviewReportsReconciliationWithoutChangingTheDatabase(): void
    {
        $request = $this->postRequest([
            'club_id' => '201',
            'operation' => 'preview',
        ]);

        $response = (new AthleteMaintenanceController($this->view, $request))->duplicates($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('DRY RUN — no changes made', $response->content());
        self::assertStringContainsString('Rossi Mario', $response->content());
        self::assertStringContainsString('#2', $response->content());
        self::assertStringContainsString('kept the higher belt', $response->content());
        self::assertStringContainsString('combined the archived and imported values', $response->content());
        self::assertSame(2, $this->athleteCount(201));
        self::assertSame('rOSSI', $this->athlete(1)['last_name']);
        self::assertSame(2, $this->entryAthlete(10));
    }

    public function testApplyRequiresPreviewBackupAndExactConfirmation(): void
    {
        $request = $this->postRequest([
            'club_id' => '201',
            'operation' => 'apply',
            'confirmation' => 'wrong',
        ]);

        $response = (new AthleteMaintenanceController($this->view, $request))->duplicates($request);

        self::assertStringContainsString('Confirm that registrations are stopped', $response->content());
        self::assertStringContainsString('confirmation phrase does not match', $response->content());
        self::assertStringContainsString('Run a fresh preview', $response->content());
        self::assertSame(2, $this->athleteCount(201));
        self::assertSame(2, $this->entryAthlete(10));
    }

    public function testPreviewThenApplyMergesOnceAndConsumesThePreviewAuthorization(): void
    {
        $preview = $this->postRequest([
            'club_id' => '201',
            'operation' => 'preview',
        ]);
        (new AthleteMaintenanceController($this->view, $preview))->duplicates($preview);
        $apply = $this->postRequest([
            'club_id' => '201',
            'operation' => 'apply',
            'backup_confirmed' => '1',
            'confirmation' => AthleteMaintenanceController::CONFIRMATION,
        ]);

        $response = (new AthleteMaintenanceController($this->view, $apply))->duplicates($apply);

        self::assertStringContainsString('APPLIED TO DATABASE', $response->content());
        self::assertStringContainsString('All reported safe groups were applied', $response->content());
        self::assertSame(1, $this->athleteCount(201));
        self::assertSame([
            'last_name' => 'Rossi',
            'first_name' => 'Mario',
            'gender' => 'M',
            'weight_kg' => 50.0,
            'belt' => 'blue',
            'membership_number' => 'OLD-001 / NEW-002',
            'notes' => "original note\nimported note",
        ], $this->athlete(1));
        self::assertSame(1, $this->entryAthlete(10));

        $repeated = (new AthleteMaintenanceController($this->view, $apply))->duplicates($apply);
        self::assertStringContainsString('Run a fresh preview', $repeated->content());
        self::assertSame(1, $this->athleteCount(201));

        $verification = $this->postRequest([
            'club_id' => '201',
            'operation' => 'preview',
        ]);
        $verified = (new AthleteMaintenanceController($this->view, $verification))->duplicates($verification);
        self::assertStringContainsString('No safe duplicate groups', $verified->content());
    }

    public function testOverlappingRegistrationsAreReportedAndRemainUnchanged(): void
    {
        $this->database->exec(
            'INSERT INTO entries (id, event_id, club_id, athlete_id) VALUES (11, 100, 201, 1)'
        );
        $preview = $this->postRequest([
            'club_id' => '201',
            'operation' => 'preview',
        ]);
        $previewResponse = (new AthleteMaintenanceController($this->view, $preview))->duplicates($preview);
        self::assertStringContainsString('Blocked groups — unchanged', $previewResponse->content());
        self::assertStringContainsString('event(s): 100', $previewResponse->content());

        $apply = $this->postRequest([
            'club_id' => '201',
            'operation' => 'apply',
            'backup_confirmed' => '1',
            'confirmation' => AthleteMaintenanceController::CONFIRMATION,
        ]);
        $applyResponse = (new AthleteMaintenanceController($this->view, $apply))->duplicates($apply);

        self::assertStringContainsString('blocked groups were left unchanged', $applyResponse->content());
        self::assertSame(2, $this->athleteCount(201));
        self::assertSame([1, 2], array_map('intval', $this->database->query(
            'SELECT athlete_id FROM entries ORDER BY athlete_id'
        )->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function testDisabledPageFailsClosedAndAnonymousUsersAreRedirected(): void
    {
        $request = new Request('GET', '/admin/maintenance/athlete-duplicates');
        $this->destroySession();
        $anonymous = (new AthleteMaintenanceController($this->view, $request))->duplicates($request);
        self::assertSame('/admin/login', $anonymous->headers()['Location']);

        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));
        $this->setMaintenanceFlag('false');
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        (new AthleteMaintenanceController($this->view, $request))->duplicates($request);
    }

    /** @param array<string, string> $data */
    private function postRequest(array $data): Request
    {
        return new Request(
            'POST',
            '/admin/maintenance/athlete-duplicates',
            [],
            array_merge(['csrf_token' => csrf_token()], $data)
        );
    }

    private function createSchema(): void
    {
        $this->database->exec(
            'CREATE TABLE clubs (
                id INTEGER PRIMARY KEY,
                federal_code TEXT NOT NULL,
                name TEXT NOT NULL,
                approved_at TEXT
            );
            CREATE TABLE athletes (
                id INTEGER PRIMARY KEY,
                club_id INTEGER NOT NULL,
                last_name TEXT NOT NULL,
                first_name TEXT NOT NULL,
                gender TEXT NOT NULL,
                birth_date TEXT NOT NULL,
                weight_kg REAL,
                belt TEXT,
                membership_number TEXT,
                notes TEXT
            );
            CREATE TABLE entries (
                id INTEGER PRIMARY KEY,
                event_id INTEGER NOT NULL,
                club_id INTEGER NOT NULL,
                athlete_id INTEGER NOT NULL,
                UNIQUE (event_id, club_id, athlete_id)
            )'
        );
    }

    private function seedData(): void
    {
        $this->database->exec(
            "INSERT INTO clubs (id, federal_code, name) VALUES
                (201, 'SYN-201', 'Synthetic Club'),
                (202, 'SYN-202', 'Other Club');
             INSERT INTO athletes
                (id, club_id, last_name, first_name, gender, birth_date, weight_kg, belt,
                 membership_number, notes)
             VALUES
                (1, 201, 'rOSSI', 'mARIO', 'M', '2012-04-05', 50, 'green',
                 'OLD-001', 'original note'),
                (2, 201, 'ROSSI', 'Mario', 'F', '2012-04-05', 55, 'blue',
                 'NEW-002', 'imported note'),
                (3, 202, 'Bianchi', 'Luca', 'M', '2011-03-02', 48, 'yellow', NULL, NULL);
             INSERT INTO entries (id, event_id, club_id, athlete_id) VALUES
                (10, 100, 201, 2)"
        );
    }

    private function athleteCount(int $clubId): int
    {
        $statement = $this->database->prepare('SELECT COUNT(*) FROM athletes WHERE club_id = ?');
        $statement->execute([$clubId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array{
     *     last_name: string,
     *     first_name: string,
     *     gender: string,
     *     weight_kg: float|null,
     *     belt: string|null,
     *     membership_number: string|null,
     *     notes: string|null
     * }
     */
    private function athlete(int $id): array
    {
        $statement = $this->database->prepare(
            'SELECT last_name, first_name, gender, weight_kg, belt, membership_number, notes
             FROM athletes WHERE id = ?'
        );
        $statement->execute([$id]);
        $athlete = $statement->fetch();
        self::assertIsArray($athlete);

        return [
            'last_name' => (string) $athlete['last_name'],
            'first_name' => (string) $athlete['first_name'],
            'gender' => (string) $athlete['gender'],
            'weight_kg' => $athlete['weight_kg'] !== null ? (float) $athlete['weight_kg'] : null,
            'belt' => $athlete['belt'] !== null ? (string) $athlete['belt'] : null,
            'membership_number' => $athlete['membership_number'] !== null
                ? (string) $athlete['membership_number']
                : null,
            'notes' => $athlete['notes'] !== null ? (string) $athlete['notes'] : null,
        ];
    }

    private function entryAthlete(int $entryId): int
    {
        $statement = $this->database->prepare('SELECT athlete_id FROM entries WHERE id = ?');
        $statement->execute([$entryId]);

        return (int) $statement->fetchColumn();
    }

    private function setMaintenanceFlag(string $value): void
    {
        $_ENV['ATHLETE_DUPLICATE_MAINTENANCE'] = $value;
        $_SERVER['ATHLETE_DUPLICATE_MAINTENANCE'] = $value;
    }

    private function restoreMaintenanceFlag(): void
    {
        if ($this->hadEnvironmentFlag) {
            $_ENV['ATHLETE_DUPLICATE_MAINTENANCE'] = $this->originalEnvironmentFlag;
        } else {
            unset($_ENV['ATHLETE_DUPLICATE_MAINTENANCE']);
        }
        if ($this->hadServerFlag) {
            $_SERVER['ATHLETE_DUPLICATE_MAINTENANCE'] = $this->originalServerFlag;
        } else {
            unset($_SERVER['ATHLETE_DUPLICATE_MAINTENANCE']);
        }
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
