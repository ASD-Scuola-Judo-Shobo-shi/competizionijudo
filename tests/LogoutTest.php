<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\AdminController;
use App\Controller\ClubController;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use PHPUnit\Framework\TestCase;

final class LogoutTest extends TestCase
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

    public function testGetLogoutRoutesDoNotMutateAuthenticationState(): void
    {
        Session::set('club_id', 201);
        Session::set('is_admin', true);
        $router = new Router($this->view, new Request('GET', '/'));
        (require dirname(__DIR__) . '/routes/web.php')($router);

        foreach (['/club_logout.php', '/admin_logout.php'] as $path) {
            try {
                $router->dispatch(new Request('GET', $path));
                self::fail('A GET logout route was registered.');
            } catch (HttpException $exception) {
                self::assertSame(404, $exception->statusCode());
            }

            self::assertSame(201, Session::get('club_id'));
            self::assertTrue(Session::get('is_admin'));
        }
    }

    public function testInvalidCsrfDoesNotLogoutClubOrAdmin(): void
    {
        Session::set('club_id', 201);
        Session::set('is_admin', true);
        csrf_token();
        $request = new Request('POST', '/logout', [], [
            'csrf_token' => 'synthetic-invalid-csrf',
        ]);

        $controllers = [
            new ClubController($this->view, $request),
            new AdminController($this->view, $request),
        ];
        foreach ($controllers as $controller) {
            try {
                $controller->logout($request);
                self::fail('Invalid CSRF logged the user out.');
            } catch (HttpException $exception) {
                self::assertSame(419, $exception->statusCode());
            }

            self::assertSame(201, Session::get('club_id'));
            self::assertTrue(Session::get('is_admin'));
        }
    }

    public function testValidClubLogoutDestroysSessionAndCookie(): void
    {
        Session::set('club_id', 201);
        $_COOKIE[session_name()] = 'synthetic-session-cookie';
        $request = new Request('POST', '/club_logout.php', [], [
            'csrf_token' => csrf_token(),
        ]);

        $response = (new ClubController($this->view, $request))->logout($request);

        self::assertSame(302, $response->status());
        $this->assertSessionWasDestroyed();
    }

    public function testValidAdminLogoutDestroysSessionAndCookie(): void
    {
        Session::set('is_admin', true);
        $_COOKIE[session_name()] = 'synthetic-session-cookie';
        $request = new Request('POST', '/admin_logout.php', [], [
            'csrf_token' => csrf_token(),
        ]);

        $response = (new AdminController($this->view, $request))->logout($request);

        self::assertSame(302, $response->status());
        $this->assertSessionWasDestroyed();
    }

    public function testLogoutControlsArePostFormsWithCsrfTokens(): void
    {
        $layout = file_get_contents(dirname(__DIR__) . '/views/layouts/app.php');
        self::assertIsString($layout);
        self::assertStringContainsString('method="post" action="/club_logout.php"', $layout);
        self::assertStringNotContainsString('href="/club_logout.php"', $layout);

        $adminLogout = $this->findLogoutItem(build_submenu('/admin_manage_events.php', true, false));
        $clubLogout = $this->findLogoutItem(build_submenu('/club_area.php', false, true));
        self::assertSame('/admin_logout.php', $adminLogout['url']);
        self::assertSame('post', $adminLogout['method'] ?? null);
        self::assertSame('/club_logout.php', $clubLogout['url']);
        self::assertSame('post', $clubLogout['method'] ?? null);
        self::assertStringContainsString('csrf_field()', $layout);
    }

    /**
     * @param list<array{label: string, url: string, paths: list<string>, method?: 'post', query?: array<string, list<string>>}> $items
     * @return array{label: string, url: string, paths: list<string>, method?: 'post', query?: array<string, list<string>>}
     */
    private function findLogoutItem(array $items): array
    {
        foreach ($items as $item) {
            if (str_ends_with($item['url'], '_logout.php')) {
                return $item;
            }
        }

        self::fail('Expected a logout submenu item.');
    }

    private function assertSessionWasDestroyed(): void
    {
        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame('', session_id());
        self::assertSame([], $_SESSION);
        self::assertArrayNotHasKey(session_name(), $_COOKIE);
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
