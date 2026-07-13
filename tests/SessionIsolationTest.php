<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Session;
use App\Core\SessionConfiguration;
use PHPUnit\Framework\TestCase;

final class SessionIsolationTest extends TestCase
{
    private SessionConfiguration $originalConfiguration;

    /** @var array<string, mixed> */
    private array $originalCookies;

    protected function setUp(): void
    {
        $this->stopSession();
        $this->originalConfiguration = Session::configuration();
        $this->originalCookies = $_COOKIE;
    }

    protected function tearDown(): void
    {
        $this->stopSession();
        $_COOKIE = $this->originalCookies;
        Session::configure($this->originalConfiguration);
    }

    public function testRootRoutePrefixProducesDistinctCookieNamespaces(): void
    {
        $originalEnvironment = $_ENV['APP_ENV'] ?? null;
        $originalRoutePrefix = $_SERVER['APP_ROUTE_PREFIX'] ?? null;

        try {
            $_ENV['APP_ENV'] = 'production';
            $_SERVER['APP_ROUTE_PREFIX'] = '/prod';
            $production = SessionConfiguration::fromEnvironment();

            $_ENV['APP_ENV'] = 'development';
            $_SERVER['APP_ROUTE_PREFIX'] = '/dev';
            $development = SessionConfiguration::fromEnvironment();

            self::assertSame('CJ_PRODUCTION_PROD_SESSION', $production->cookieName);
            self::assertSame('CJ_DEVELOPMENT_DEV_SESSION', $development->cookieName);
            self::assertSame('/prod', $production->cookiePath);
            self::assertSame('/dev', $development->cookiePath);
            self::assertNotSame($production->context, $development->context);
        } finally {
            if ($originalEnvironment === null) {
                unset($_ENV['APP_ENV']);
            } else {
                $_ENV['APP_ENV'] = $originalEnvironment;
            }

            if ($originalRoutePrefix === null) {
                unset($_SERVER['APP_ROUTE_PREFIX']);
            } else {
                $_SERVER['APP_ROUTE_PREFIX'] = $originalRoutePrefix;
            }
        }
    }

    public function testCopiedSessionIdCannotCrossEnvironmentBoundary(): void
    {
        $production = new SessionConfiguration('production', '/prod');
        Session::configure($production);
        Session::start();
        Session::set('is_admin', true);
        $productionId = session_id();
        self::assertNotSame('', $productionId);
        session_write_close();

        $development = new SessionConfiguration('development', '/dev');
        Session::configure($development);
        $_COOKIE[$development->cookieName] = $productionId;
        Session::start();

        self::assertSame($development->cookieName, session_name());
        self::assertSame('/dev', session_get_cookie_params()['path']);
        self::assertSame('1', ini_get('session.use_strict_mode'));
        self::assertNull(Session::get('is_admin'));
        self::assertSame($development->context, $_SESSION['_session_context']);
        self::assertNotSame($productionId, session_id());
    }

    public function testRootRouterPassesTheEnvironmentPrefixToTheApplication(): void
    {
        $directory = sys_get_temp_dir() . '/competizioni-judo-root-router-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory . '/prod/public', 0700, true));
        self::assertTrue(mkdir($directory . '/dev/public', 0700, true));
        self::assertTrue(copy(dirname(__DIR__) . '/index.php', $directory . '/index.php'));
        $entryPoint = "<?php echo json_encode(['prefix' => \$_SERVER['APP_ROUTE_PREFIX'] ?? null, 'uri' => \$_SERVER['REQUEST_URI'] ?? null]);";
        file_put_contents($directory . '/prod/public/index.php', $entryPoint);
        file_put_contents($directory . '/dev/public/index.php', $entryPoint);

        try {
            foreach (['prod', 'dev'] as $environment) {
                self::assertSame([
                    'prefix' => '/' . $environment,
                    'uri' => '/health?probe=1',
                ], $this->dispatchRootRouter($directory, $environment));
            }
        } finally {
            @unlink($directory . '/prod/public/index.php');
            @rmdir($directory . '/prod/public');
            @rmdir($directory . '/prod');
            @unlink($directory . '/dev/public/index.php');
            @rmdir($directory . '/dev/public');
            @rmdir($directory . '/dev');
            @unlink($directory . '/index.php');
            @rmdir($directory);
        }
    }

    /** @return array{prefix: string, uri: string} */
    private function dispatchRootRouter(string $directory, string $environment): array
    {
        $code = '$_SERVER = ' . var_export([
            'HTTPS' => 'on',
            'HTTP_HOST' => 'www.competizionijudo.it',
            'REQUEST_URI' => '/' . $environment . '/health?probe=1',
        ], true) . '; require ' . var_export($directory . '/index.php', true) . ';';
        $process = proc_open(
            [PHP_BINARY, '-r', $code],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        self::assertSame(0, proc_close($process), $error);

        return json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    }

    private function stopSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            Session::destroy();
        }

        $_SESSION = [];
        session_id('');
    }
}
