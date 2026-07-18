<?php

declare(strict_types=1);

namespace Tests;

use App\Presentation\Navigation;
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
        Session::authenticateAdministrator();
        $router = new Router($this->view);
        (require dirname(__DIR__) . '/routes/web.php')($router);

        foreach (['/clubs/logout', '/admin/logout'] as $path) {
            $previousClubId = Session::get('club_id');
            $previousIsAdmin = Session::get('is_admin');

            try {
                $router->dispatch(new Request('GET', $path));
                self::fail('A GET logout route was registered.');
            } catch (HttpException $exception) {
                self::assertSame(405, $exception->statusCode());
                self::assertSame('POST', $exception->headers()['Allow']);
            }

            self::assertSame($previousClubId, Session::get('club_id'));
            self::assertSame($previousIsAdmin, Session::get('is_admin'));
        }
    }

    public function testInvalidCsrfDoesNotLogoutClubOrAdmin(): void
    {
        Session::authenticateAdministrator();
        csrf_token();
        $request = new Request('POST', '/clubs/logout', [], [
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

            self::assertTrue(Session::get('is_admin'));
        }
    }

    public function testValidClubLogoutDestroysSessionAndCookie(): void
    {
        Session::authenticateClub(201);
        $_COOKIE[session_name()] = 'synthetic-session-cookie';
        $request = new Request('POST', '/clubs/logout', [], [
            'csrf_token' => csrf_token(),
        ]);

        $response = (new ClubController($this->view, $request))->logout($request);

        self::assertSame(302, $response->status());
        $this->assertSessionWasDestroyed();
    }

    public function testValidAdminLogoutDestroysSessionAndCookie(): void
    {
        Session::authenticateAdministrator();
        $_COOKIE[session_name()] = 'synthetic-session-cookie';
        $request = new Request('POST', '/admin/logout', [], [
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
        self::assertStringContainsString('method="post" action="<?= e(base_url(\'/clubs/logout\')) ?>"', $layout);
        self::assertStringNotContainsString('href="/clubs/logout"', $layout);

        $adminLogout = $this->findLogoutItem(Navigation::submenu('/admin/events', true, false));
        $clubLogout = $this->findLogoutItem(Navigation::submenu('/clubs/area', false, true));
        self::assertSame('/admin/logout', $adminLogout['url']);
        self::assertSame('post', $adminLogout['method'] ?? null);
        self::assertSame('/clubs/logout', $clubLogout['url']);
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
            if (str_ends_with($item['url'], '/logout')) {
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
