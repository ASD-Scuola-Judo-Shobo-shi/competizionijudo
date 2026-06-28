<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\AdminController;
use App\Controller\ClubController;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeAuthenticationThrottle;

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
        $request = new Request('POST', '/admin_login.php', [], [
            'csrf_token' => csrf_token(),
            'user' => 'wrong',
            'pass' => 'wrong',
        ], ['REMOTE_ADDR' => '192.0.2.10']);
        $throttle = new FakeAuthenticationThrottle();

        $controller = new AdminController($this->view, $request, $throttle);
        $response = $controller->login($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Credenziali amministratore non valide', $response->content());
        self::assertSame([[
            'scope' => 'admin-login',
            'account' => 'wrong',
            'network' => '192.0.2.10',
        ]], $throttle->recorded);
    }

    public function testAdminLoginRemainsBlockedAfterStartingANewBrowserSession(): void
    {
        $throttle = new FakeAuthenticationThrottle();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $throttle->recordAttempt('admin-login', 'wrong', '192.0.2.20');
        }

        $this->startCleanSession();

        $request = new Request('POST', '/admin_login.php', [], [
            'csrf_token' => csrf_token(),
            'user' => 'wrong',
            'pass' => 'wrong',
        ], ['REMOTE_ADDR' => '192.0.2.20']);

        $controller = new AdminController($this->view, $request, $throttle);
        $response = $controller->login($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e(__('admin.login.errors.too_many_attempts')), $response->content());
        self::assertCount(5, $throttle->recorded);
    }

    public function testClubLoginUsesPersistentThrottleBeforeAccountLookup(): void
    {
        $throttle = new FakeAuthenticationThrottle(1);
        $throttle->recordAttempt('club-login', 'club@example.test', '192.0.2.30');
        $this->startCleanSession();

        $request = new Request('POST', '/club_login.php', [], [
            'csrf_token' => csrf_token(),
            'email' => 'club@example.test',
            'password' => 'wrong',
        ], ['REMOTE_ADDR' => '192.0.2.30']);
        $controller = new ClubController($this->view, $request, null, $throttle);

        $response = $controller->login($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e(__('club.login.errors.too_many_attempts')), $response->content());
        self::assertCount(1, $throttle->recorded);
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
