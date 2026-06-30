<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
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
    }

    public function testUnknownPathRemainsNotFound(): void
    {
        $router = new Router(new View(dirname(__DIR__) . '/views'));

        try {
            $router->dispatch(new Request('GET', '/missing'));
            self::fail('Missing route did not throw.');
        } catch (HttpException $exception) {
            self::assertSame(404, $exception->statusCode());
        }
    }
}
