<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testDispatchesRegisteredGetRoute(): void
    {
        $router = new Router(new View(dirname(__DIR__) . '/views'), new Request('GET', '/'));
        $router->get('/health', static fn (Request $request): Response => new Response('ok'));

        $response = $router->dispatch(new Request('GET', '/health'));

        self::assertSame(200, $response->status());
        self::assertSame('ok', $response->content());
    }
}
