<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\AdminController;
use App\Controller\ClubAreaController;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Model\Database;
use App\Service\EventUploadStorage;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class DeleteActionsTest extends TestCase
{
    private ReflectionProperty $databaseConnection;
    private ?PDO $originalConnection;
    private View $view;

    protected function setUp(): void
    {
        $this->databaseConnection = new ReflectionProperty(Database::class, 'pdo');
        $connection = $this->databaseConnection->getValue();
        self::assertTrue($connection === null || $connection instanceof PDO);
        $this->originalConnection = $connection;

        $this->startCleanSession();
        $this->view = new View(dirname(__DIR__) . '/views');
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
        $this->destroySession();
    }

    public function testDeleteRoutesAcceptOnlyPostAndRequireAuthentication(): void
    {
        $router = new Router($this->view);
        (require dirname(__DIR__) . '/routes/web.php')($router);
        $paths = [
            '/admin_delete_club.php',
            '/admin_delete_event.php',
            '/club_delete_athlete.php',
        ];

        foreach ($paths as $path) {
            try {
                $router->dispatch(new Request('GET', $path));
                self::fail('A destructive GET route was registered.');
            } catch (HttpException $exception) {
                self::assertSame(405, $exception->statusCode());
                self::assertSame('POST', $exception->headers()['Allow']);
            }

            $response = $router->dispatch(new Request('POST', $path));
            self::assertSame(302, $response->status(), strip_tags($response->content()));
        }
    }

    public function testInvalidCsrfRejectsEveryDeleteBeforeDatabaseAccess(): void
    {
        $this->setDatabase($this->databaseExpectingNoAccess());
        Session::set('is_admin', true);
        $adminRequest = new Request('POST', '/delete', [], [
            'csrf_token' => 'synthetic-invalid-csrf',
            'club_id' => '401',
            'event_id' => '501',
        ]);
        $admin = new AdminController($this->view, $adminRequest);

        $this->assertCsrfRejected(static fn(): Response => $admin->deleteClub($adminRequest));
        $this->assertCsrfRejected(static fn(): Response => $admin->deleteEvent($adminRequest));

        Session::set('club_id', 201);
        $clubRequest = new Request('POST', '/club_delete_athlete.php', [], [
            'csrf_token' => 'synthetic-invalid-csrf',
            'athlete_id' => '301',
        ]);
        $clubArea = new ClubAreaController($this->view, $clubRequest);
        $this->assertCsrfRejected(static fn(): Response => $clubArea->deleteAthlete($clubRequest));
    }

    public function testValidAdminRequestDeletesClub(): void
    {
        Session::set('is_admin', true);
        $this->setDatabase($this->databaseExpectingDelete('DELETE FROM clubs WHERE id = ?', [401]));
        $request = new Request('POST', '/admin_delete_club.php', [], [
            'csrf_token' => csrf_token(),
            'club_id' => '401',
        ]);

        $response = (new AdminController($this->view, $request))->deleteClub($request);

        self::assertSame(302, $response->status());
    }

    public function testValidAdminRequestDeletesEvent(): void
    {
        Session::set('is_admin', true);
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createEventTable($database);
        $database->exec(
            "INSERT INTO events
             (id, name, date, location, organizer, registration_deadline, type, description, notes,
              poster_file, info_file, published, closed)
             VALUES
             (501, 'Synthetic event', '2026-07-01', 'Test city', '', '', 'only_competitive', '', '',
              'uploads/events/old-poster.pdf', 'uploads/events/old-info.pdf', 1, 0)"
        );
        $this->setDatabase($database);
        $publicRoot = sys_get_temp_dir() . '/competizionijudo-delete-' . bin2hex(random_bytes(8));
        mkdir($publicRoot . '/uploads/events', 0755, true);
        file_put_contents($publicRoot . '/uploads/events/old-poster.pdf', 'synthetic');
        file_put_contents($publicRoot . '/uploads/events/old-info.pdf', 'synthetic');
        $request = new Request('POST', '/admin_delete_event.php', [], [
            'csrf_token' => csrf_token(),
            'event_id' => '501',
        ]);

        $response = (new AdminController(
            $this->view,
            $request,
            null,
            null,
            null,
            new EventUploadStorage($publicRoot)
        ))->deleteEvent($request);

        self::assertSame(302, $response->status());
        self::assertSame(0, (int) $database->query('SELECT COUNT(*) FROM events')->fetchColumn());
        self::assertFileDoesNotExist($publicRoot . '/uploads/events/old-poster.pdf');
        self::assertFileDoesNotExist($publicRoot . '/uploads/events/old-info.pdf');
        rmdir($publicRoot . '/uploads/events');
        rmdir($publicRoot . '/uploads');
        rmdir($publicRoot);
    }

    public function testReplacingEventUploadPurgesPreviousFile(): void
    {
        Session::set('is_admin', true);
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createEventTable($database);
        $database->exec(
            "INSERT INTO events
             (id, name, date, location, organizer, registration_deadline, type, description, notes,
              poster_file, info_file, published, closed)
             VALUES
             (502, 'Synthetic event', '2026-07-01', 'Test city', '', '', 'only_competitive', '', '',
              'uploads/events/old-poster.png', NULL, 1, 0)"
        );
        $this->setDatabase($database);

        $publicRoot = sys_get_temp_dir() . '/competizionijudo-replace-' . bin2hex(random_bytes(8));
        mkdir($publicRoot . '/uploads/events', 0755, true);
        file_put_contents($publicRoot . '/uploads/events/old-poster.png', 'synthetic old image');
        $temporaryUpload = $publicRoot . '/new-poster.png';
        file_put_contents(
            $temporaryUpload,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true)
        );
        $_FILES['poster_file'] = [
            'name' => 'new-poster.png',
            'type' => 'image/png',
            'tmp_name' => $temporaryUpload,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($temporaryUpload),
        ];
        $request = new Request('POST', '/admin_add_event.php', [], [
            'csrf_token' => csrf_token(),
            'event_id' => '502',
            'name' => 'Synthetic event',
            'date' => '2026-07-01',
            'location' => 'Test city',
            'organizer' => '',
            'registration_deadline' => '',
            'type' => 'only_competitive',
            'description' => '',
            'notes' => '',
            'published' => '1',
            'closed' => '0',
        ]);
        $storage = new EventUploadStorage(
            $publicRoot,
            static function (string $source, string $destination): bool {
                return rename($source, $destination);
            }
        );

        try {
            $response = (new AdminController(
                $this->view,
                $request,
                null,
                null,
                null,
                $storage
            ))->addEvent($request);

            self::assertSame(302, $response->status(), strip_tags($response->content()));
            self::assertFileDoesNotExist($publicRoot . '/uploads/events/old-poster.png');
            $storedPath = (string) $database->query('SELECT poster_file FROM events WHERE id = 502')->fetchColumn();
            self::assertMatchesRegularExpression('#^uploads/events/poster_.*\.png$#', $storedPath);
            self::assertFileExists($publicRoot . '/' . $storedPath);
        } finally {
            unset($_FILES['poster_file']);
            foreach (glob($publicRoot . '/uploads/events/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($publicRoot . '/uploads/events');
            rmdir($publicRoot . '/uploads');
            rmdir($publicRoot);
        }
    }

    public function testValidClubRequestScopesAthleteDeleteToSessionClub(): void
    {
        Session::set('club_id', 201);
        $this->setDatabase($this->databaseExpectingDelete(
            'DELETE FROM athletes WHERE id = ? AND club_id = ?',
            [301, 201]
        ));
        $request = new Request('POST', '/club_delete_athlete.php', [], [
            'csrf_token' => csrf_token(),
            'athlete_id' => '301',
            'club_id' => '999',
        ]);

        $response = (new ClubAreaController($this->view, $request))->deleteAthlete($request);

        self::assertSame(302, $response->status());
    }

    public function testDeleteControlsAreCsrfProtectedForms(): void
    {
        $templates = [
            'views/admin/manage_clubs.php' => ['/admin_delete_club.php', 'club_id'],
            'views/admin/manage_events.php' => ['/admin_delete_event.php', 'event_id'],
            'views/club/area_list.php' => ['/club_delete_athlete.php', 'athlete_id'],
            'views/club/area_add.php' => ['/club_delete_athlete.php', 'athlete_id'],
        ];

        foreach ($templates as $path => [$action, $idField]) {
            $template = file_get_contents(dirname(__DIR__) . '/' . $path);
            self::assertIsString($template);
            self::assertStringContainsString('method="post" action="' . $action . '"', $template);
            self::assertStringContainsString('csrf_field()', $template);
            self::assertStringContainsString('name="' . $idField . '"', $template);
            self::assertStringNotContainsString('?delete=', $template);
        }

        self::assertStringContainsString(
            'live athlete records',
            Localization::transFor('en', 'admin.clubs.confirm_delete')
        );
        self::assertStringContainsString(
            'Esportali prima',
            Localization::transFor('it', 'admin.clubs.confirm_delete')
        );
    }

    private function assertCsrfRejected(callable $delete): void
    {
        try {
            $delete();
            self::fail('Expected CSRF validation to reject the deletion.');
        } catch (HttpException $exception) {
            self::assertSame(419, $exception->statusCode());
        }
    }

    private function databaseExpectingNoAccess(): PDO&MockObject
    {
        $database = $this->createMock(PDO::class);
        $database->expects(self::never())->method('prepare');
        $database->expects(self::never())->method('query');

        return $database;
    }

    /** @param list<int> $parameters */
    private function databaseExpectingDelete(string $sql, array $parameters): PDO&MockObject
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())->method('execute')->with($parameters)->willReturn(true);

        $database = $this->createMock(PDO::class);
        $database->expects(self::once())->method('prepare')->with($sql)->willReturn($statement);
        $database->expects(self::never())->method('query');

        return $database;
    }

    private function setDatabase(PDO $database): void
    {
        $this->databaseConnection->setValue(null, $database);
    }

    private function createEventTable(PDO $database): void
    {
        $database->exec(
            'CREATE TABLE events (
                id INTEGER PRIMARY KEY, name TEXT NOT NULL, date TEXT NOT NULL, location TEXT NOT NULL,
                organizer TEXT NOT NULL, registration_deadline TEXT, type TEXT NOT NULL,
                description TEXT, notes TEXT, poster_file TEXT, info_file TEXT,
                published INTEGER NOT NULL, closed INTEGER NOT NULL
            )'
        );
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
