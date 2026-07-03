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
    /** @var array<string, mixed> */
    private array $originalEnv;
    /** @var array<string, mixed> */
    private array $originalCookie;
    /** @var array<string, string|null> */
    private array $originalPutenv = [];

    protected function setUp(): void
    {
        $this->originalGet = $_GET;
        $this->originalServer = $_SERVER;
        $this->originalEnv = $_ENV;
        $this->originalCookie = $_COOKIE;
        $this->originalPutenv = [];
        Session::destroy();
        Session::start();
        Localization::setLocale('it');
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
        $_SERVER = $this->originalServer;
        $_ENV = $this->originalEnv;
        $_COOKIE = $this->originalCookie;
        foreach ($this->originalPutenv as $key => $value) {
            if ($value === null) {
                putenv($key);
            } else {
                putenv($key . '=' . $value);
            }
        }
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

    public function testRefererPathStripsEnvironmentPrefixWhenBasePathIsSet(): void
    {
        $this->originalPutenv['APP_URL'] = getenv('APP_URL') ?: null;
        // Simulate APP_URL=http://localhost:8000/prod so that app_base_path() returns '/prod'
        $_ENV['APP_URL'] = 'http://localhost:8000/prod';
        $_SERVER['APP_URL'] = 'http://localhost:8000/prod';
        putenv('APP_URL=http://localhost:8000/prod');

        $request = new Request('GET', '/language/switch', ['locale' => 'en'], [], [
            'HTTP_REFERER' => '/prod/events.php?page=2',
        ]);

        $response = $this->router()->dispatch($request);

        // redirect() prepends app_base_path() (/prod) via base_url()
        self::assertSame('/prod/events.php?page=2', $response->headers()['Location']);
    }

    public function testRefererPathIsRootWhenPathEqualsBasePath(): void
    {
        $this->originalPutenv['APP_URL'] = getenv('APP_URL') ?: null;
        $_ENV['APP_URL'] = 'http://localhost:8000/prod';
        $_SERVER['APP_URL'] = 'http://localhost:8000/prod';
        putenv('APP_URL=http://localhost:8000/prod');

        $request = new Request('GET', '/language/switch', ['locale' => 'en'], [], [
            'HTTP_REFERER' => '/prod',
        ]);

        $response = $this->router()->dispatch($request);

        // redirect() prepends app_base_path() (/prod) via base_url()
        self::assertSame('/prod/', $response->headers()['Location']);
    }

    private function router(): Router
    {
        $router = new Router(new View(dirname(__DIR__) . '/views'));
        $router->get('/language/switch', [LanguageController::class, 'switch']);

        return $router;
    }
}
