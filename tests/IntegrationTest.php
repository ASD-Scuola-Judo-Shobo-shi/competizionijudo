<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\AdminController;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        $this->startCleanSession();
        $this->view = new View(dirname(__DIR__) . '/views');
    }

    protected function tearDown(): void
    {
        $this->destroySession();
    }

    public function testLoginAttemptStateDoesNotLeakAcrossSessionResets(): void
    {
        Session::set('admin_login_attempts', 5);
        Session::set('club_login_attempts', 4);

        $this->startCleanSession();

        self::assertSame(0, Session::get('admin_login_attempts', 0));
        self::assertSame(0, Session::get('club_login_attempts', 0));
    }

    public function testAdminLoginShowsErrorOnInvalidCredentials(): void
    {
        Session::set('admin_login_attempts', 0);

        $request = new Request('POST', '/admin_login.php', [], [
            'csrf_token' => csrf_token(),
            'user' => 'wrong',
            'pass' => 'wrong',
        ]);

        $controller = new AdminController($this->view, $request);
        $response = $controller->login($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Credenziali amministratore non valide', $response->content());
    }

    public function testAdminLoginBlocksAfterTooManyAttempts(): void
    {
        Session::set('admin_login_attempts', 5);
        Session::set('admin_login_last_attempt', time());

        $request = new Request('POST', '/admin_login.php', [], [
            'csrf_token' => csrf_token(),
            'user' => 'wrong',
            'pass' => 'wrong',
        ]);

        $controller = new AdminController($this->view, $request);
        $response = $controller->login($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Troppi tentativi di accesso', $response->content());
    }

    public function testAdminAddEventRequiresAuth(): void
    {
        Session::destroy();

        $request = new Request('GET', '/admin_add_event.php');
        $controller = new AdminController($this->view, $request);

        $response = $controller->addEvent($request);
        self::assertSame(302, $response->status());
    }

    public function testAdminManageEventsRequiresAuth(): void
    {
        Session::destroy();

        $request = new Request('GET', '/admin_manage_events.php');
        $controller = new AdminController($this->view, $request);

        $response = $controller->manageEvents($request);
        self::assertSame(302, $response->status());
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
