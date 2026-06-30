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
use App\Model\Database;
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
            self::assertSame(302, $response->status());
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
        $this->setDatabase($this->databaseExpectingDelete('DELETE FROM events WHERE id = ?', [501]));
        $request = new Request('POST', '/admin_delete_event.php', [], [
            'csrf_token' => csrf_token(),
            'event_id' => '501',
        ]);

        $response = (new AdminController($this->view, $request))->deleteEvent($request);

        self::assertSame(302, $response->status());
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
