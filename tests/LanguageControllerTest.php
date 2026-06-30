<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\LanguageController;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use PHPUnit\Framework\TestCase;

final class LanguageControllerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalGet;
    /** @var array<string, mixed> */
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalGet = $_GET;
        $this->originalServer = $_SERVER;
        Session::destroy();
        Session::start();
        Localization::setLocale('it');
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
        $_SERVER = $this->originalServer;
        Session::destroy();
    }

    public function testSwitchUsesDispatchedRequestAndReturnsSameOriginRedirect(): void
    {
        $_GET['locale'] = 'it';
        $_SERVER['HTTP_REFERER'] = 'https://attacker.example/ignored';
        $request = new Request(
            'GET',
            '/language/switch?locale=en',
            ['locale' => 'en'],
            [],
            [
                'HTTP_HOST' => 'judo.example.test',
                'HTTP_REFERER' => 'https://judo.example.test/events.php?page=2',
            ]
        );

        $response = $this->router()->dispatch($request);

        self::assertSame(302, $response->status());
        self::assertSame('/events.php?page=2', $response->headers()['Location']);
        self::assertSame('en', Session::get('locale'));
        self::assertSame('en', Localization::getLocale());
    }

    public function testExternalRefererIsRejected(): void
    {
        $request = new Request('GET', '/language/switch', ['locale' => 'en'], [], [
            'HTTP_HOST' => 'judo.example.test',
            'HTTP_REFERER' => 'https://attacker.example/steal',
        ]);

        $response = $this->router()->dispatch($request);

        self::assertSame('/', $response->headers()['Location']);
    }

    public function testInvalidLocaleFallsBackToItalianAndRelativeRefererIsPreserved(): void
    {
        $request = new Request('GET', '/language/switch', ['locale' => 'forged'], [], [
            'HTTP_REFERER' => '/club_area.php?view=list',
        ]);

        $response = $this->router()->dispatch($request);

        self::assertSame('/club_area.php?view=list', $response->headers()['Location']);
        self::assertSame('it', Session::get('locale'));
        self::assertSame('it', Localization::getLocale());
    }

    private function router(): Router
    {
        $router = new Router(new View(dirname(__DIR__) . '/views'));
        $router->get('/language/switch', [LanguageController::class, 'switch']);

        return $router;
    }
}
