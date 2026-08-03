<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Application;
use App\Core\AuthContext;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use PHPUnit\Framework\TestCase;
use Tests\Support\RequestIdentityController;

final class RouterTest extends TestCase
{
    public function testCallableReceivesTheDispatchedRequestInstance(): void
    {
        $router = new Router(new View(dirname(__DIR__) . '/views'));
        $received = null;
        $router->get('/health', static function (Request $request) use (&$received): Response {
            $received = $request;

            return new Response('ok');
        });
        $dispatched = new Request('GET', '/health');

        $response = $router->dispatch($dispatched);

        self::assertSame($dispatched, $received);
        self::assertSame(200, $response->status());
        self::assertSame('ok', $response->content());
    }

    public function testControllerConstructorAndActionReceiveTheDispatchedRequestInstance(): void
    {
        $router = new Router(new View(dirname(__DIR__) . '/views'));
        $router->get('/identity', [RequestIdentityController::class, 'show']);
        $dispatched = new Request('GET', '/identity');

        $response = $router->dispatch($dispatched);

        self::assertSame('same-request', $response->content());
    }

    public function testDeclaredRoutePoliciesRejectTheWrongPrincipalBeforeTheHandler(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            Session::destroy();
        }

        $router = new Router(new View(dirname(__DIR__) . '/views'));
        $handled = [];
        $router->get('/club-only', static function (Request $request) use (&$handled): Response {
            $handled[] = 'club';

            return new Response('club');
        }, AuthContext::CLUB);
        $router->get('/admin-only', static function (Request $request) use (&$handled): Response {
            $handled[] = 'admin';

            return new Response('admin');
        }, AuthContext::ADMINISTRATOR);

        try {
            self::assertSame(302, $router->dispatch(new Request('GET', '/club-only'))->status());
            self::assertSame([], $handled);

            Session::authenticateClub(42, hash('sha256', 'test-club-credential'));
            self::assertSame(200, $router->dispatch(new Request('GET', '/club-only'))->status());
            self::assertSame(302, $router->dispatch(new Request('GET', '/admin-only'))->status());
            self::assertSame(['club'], $handled);

            Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));
            self::assertSame(302, $router->dispatch(new Request('GET', '/club-only'))->status());
            self::assertSame(200, $router->dispatch(new Request('GET', '/admin-only'))->status());
            self::assertSame(['club', 'admin'], $handled);
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                Session::destroy();
            }
        }
    }

    public function testRequestPathStripsConfiguredApplicationBasePath(): void
    {
        $_ENV['APP_URL'] = 'https://example.test/prod';
        $_SERVER['APP_URL'] = 'https://example.test/prod';

        $request = new Request('GET', '/prod/admin_manage_events.php?page=2');

        self::assertSame('/admin_manage_events.php', $request->path());

        unset($_ENV['APP_URL'], $_SERVER['APP_URL']);
    }

    public function testKnownPathWithWrongMethodReturnsRendered405AndAllowHeader(): void
    {
        Localization::setLocale('en');
        $application = new Application(dirname(__DIR__));
        $application->router()->get('/method-test', static fn(Request $request): Response => new Response('get'));
        $application->router()->post('/method-test', static fn(Request $request): Response => new Response('post'));

        $response = $application->handle(new Request('DELETE', '/method-test'));

        self::assertSame(405, $response->status());
        self::assertSame('GET, POST', $response->headers()['Allow']);
        self::assertStringContainsString('Method not allowed', $response->content());
        self::assertStringContainsString(
            e(__('errors.method_not_allowed_description')),
            $response->content()
        );
        self::assertStringContainsString('class="content-panel error-card"', $response->content());
        self::assertSame('nosniff', $response->headers()['X-Content-Type-Options']);
        self::assertArrayHasKey('Content-Security-Policy', $response->headers());
        self::assertArrayNotHasKey('Content-Security-Policy-Report-Only', $response->headers());
    }

    public function testUnknownPathReturnsLocalizedRecoveryPage(): void
    {
        Localization::setLocale('it');
        $application = new Application(dirname(__DIR__));

        $response = $application->handle(new Request('GET', '/missing'));

        self::assertSame(404, $response->status());
        self::assertStringContainsString(e(__('errors.page_not_found')), $response->content());
        self::assertStringContainsString(
            e(__('errors.page_not_found_description')),
            $response->content()
        );
        self::assertStringContainsString(e(__('errors.go_home')), $response->content());
    }
}
