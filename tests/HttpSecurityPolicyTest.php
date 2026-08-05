<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpSecurityPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        $this->destroySession();
        Session::start();
    }

    protected function tearDown(): void
    {
        $this->destroySession();
    }

    #[DataProvider('sensitiveArtifactPaths')]
    public function testRootApachePolicyDeniesSensitiveArtifactPaths(string $path): void
    {
        self::assertTrue($this->isForbiddenByRootPolicy($path), $path . ' was not denied.');
    }

    /** @return iterable<string, array{string}> */
    public static function sensitiveArtifactPaths(): iterable
    {
        foreach (['', 'prod/', 'dev/', 'legacy/'] as $prefix) {
            yield $prefix . ' environment' => [$prefix . '.env'];
            yield $prefix . ' maintenance marker' => [$prefix . '.maintenance'];
            yield $prefix . ' composer metadata' => [$prefix . 'composer.json'];
            yield $prefix . ' migration' => [$prefix . 'migrations/20260630_000000_create_schema.sql'];
            yield $prefix . ' source' => [$prefix . 'src/Core/Application.php'];
            yield $prefix . ' revision' => [$prefix . 'REVISION'];
            yield $prefix . ' manifest' => [$prefix . 'DEPLOYMENT_MANIFEST.sha256'];
        }
    }

    public function testRootApachePolicyKeepsPublicPathsReachable(): void
    {
        foreach (['prod/events', 'prod/assets/logo.svg', 'dev/health', '.well-known/acme-challenge/token'] as $path) {
            self::assertFalse($this->isForbiddenByRootPolicy($path), $path . ' was unexpectedly denied.');
        }
    }

    public function testRootApachePolicyDispatchesOnlyExactMigrationEndpointsBeforeDeny(): void
    {
        $policy = $this->rootPolicy();
        $dispatch = 'RewriteRule ^(?:prod|dev)/migrations/?$ index.php [QSA,L]';
        $dispatchPosition = strpos($policy, $dispatch);
        $denyPosition = strpos(
            $policy,
            'RewriteRule ^(?:(?:prod|dev|legacy)/)?(?:\.git|\.github|config|docs|lang|migrations|'
        );

        self::assertIsInt($dispatchPosition);
        self::assertIsInt($denyPosition);
        self::assertTrue($dispatchPosition < $denyPosition);
        self::assertStringNotContainsString('RewriteCond %{REQUEST_METHOD}', $policy);
        self::assertTrue($this->isForbiddenByRootPolicy('prod/migrations/20260630_000000_create_schema.sql'));
        self::assertTrue($this->isForbiddenByRootPolicy('legacy/migrations/'));
        self::assertStringNotContainsString('(?:prod|dev|legacy)/migrations/?$', $policy);
    }

    #[DataProvider('directArtifactPaths')]
    public function testDirectArtifactApachePolicyDeniesSensitivePaths(string $path): void
    {
        self::assertTrue($this->isForbiddenByArtifactPolicy($path), $path . ' was not denied.');
    }

    /** @return iterable<string, array{string}> */
    public static function directArtifactPaths(): iterable
    {
        yield 'environment' => ['.env'];
        yield 'maintenance marker' => ['.maintenance'];
        yield 'composer metadata' => ['composer.json'];
        yield 'migration' => ['migrations/20260630_000000_create_schema.sql'];
        yield 'source' => ['src/Core/Application.php'];
        yield 'revision' => ['REVISION'];
        yield 'manifest' => ['DEPLOYMENT_MANIFEST.sha256'];
    }

    public function testDirectArtifactApachePolicyKeepsPublicPathsReachable(): void
    {
        foreach (['events', 'assets/logo.svg', 'health', '.well-known/acme-challenge/token'] as $path) {
            self::assertFalse($this->isForbiddenByArtifactPolicy($path), $path . ' was unexpectedly denied.');
        }
    }

    public function testArtifactApachePolicyDispatchesOnlyExactMigrationEndpointsBeforeDeny(): void
    {
        $policy = $this->artifactPolicy();
        $dispatch = 'RewriteRule ^(?:migrations/?)$ public/index.php [QSA,L]';
        $dispatchPosition = strpos($policy, $dispatch);
        $denyPosition = strpos($policy, 'RewriteRule ^(?:\.git|\.github|config|docs|lang|migrations|');

        self::assertIsInt($dispatchPosition);
        self::assertIsInt($denyPosition);
        self::assertTrue($dispatchPosition < $denyPosition);
        self::assertStringNotContainsString('RewriteCond %{REQUEST_METHOD}', $policy);
        self::assertTrue($this->isForbiddenByArtifactPolicy('migrations/20260630_000000_create_schema.sql'));
        self::assertTrue($this->isForbiddenByArtifactPolicy('migrations/nested/file.sql'));
    }

    public function testApacheHttpsRedirectUsesTheCanonicalHost(): void
    {
        $policy = $this->rootPolicy();

        self::assertStringContainsString(
            'RewriteRule ^ https://www.competizionijudo.it%{REQUEST_URI} [L,R=301]',
            $policy
        );
        foreach (['prod', 'dev', 'legacy'] as $environment) {
            self::assertStringContainsString(
                'RewriteRule ^ https://www.competizionijudo.it/' . $environment
                    . '%{REQUEST_URI} [L,R=301]',
                $policy
            );
        }
        self::assertStringNotContainsString('https://%{HTTP_HOST}', $policy);
        self::assertStringContainsString('RewriteRule ^ - [R=400,L]', $policy);
    }

    public function testApplicationEnforcesBrowserPolicyOnErrorResponses(): void
    {
        $response = (new Application(dirname(__DIR__)))->handle(new Request('GET', '/missing'));

        self::assertArrayHasKey('Content-Security-Policy', $response->headers());
        self::assertArrayNotHasKey('Content-Security-Policy-Report-Only', $response->headers());
        self::assertStringContainsString("frame-ancestors 'none'", $response->headers()['Content-Security-Policy']);
        self::assertSame('DENY', $response->headers()['X-Frame-Options']);
        self::assertSame('0', $response->headers()['X-XSS-Protection']);
    }

    public function testCspUsesPerRequestNoncesAndBansInlineScriptsAndStyles(): void
    {
        $response = (new Application(dirname(__DIR__)))->handle(new Request('GET', '/missing'));
        $policy = $response->headers()['Content-Security-Policy'];

        self::assertStringNotContainsString('unsafe-inline', $policy);
        self::assertMatchesRegularExpression(
            "/script-src 'self' 'nonce-[0-9a-f]{32}'/",
            $policy
        );
        self::assertMatchesRegularExpression(
            "/style-src 'self' 'nonce-[0-9a-f]{32}'/",
            $policy
        );

        preg_match("/script-src 'self' 'nonce-([0-9a-f]{32})'/", $policy, $matches);
        $nonce = $matches[1];
        $body = $response->content();

        self::assertMatchesRegularExpression('/<script nonce="' . preg_quote($nonce, '/') . '">/', $body);
        self::assertMatchesRegularExpression('/<style nonce="' . preg_quote($nonce, '/') . '">/', $body);
        self::assertStringNotContainsString('<script nonce="">', $body);

        preg_match_all('/<script nonce="[0-9a-f]{32}">/', $body, $scriptMatches);
        preg_match_all('/<style nonce="[0-9a-f]{32}">/', $body, $styleMatches);
        self::assertNotEmpty($scriptMatches[0]);
        self::assertNotEmpty($styleMatches[0]);
    }

    public function testCspNonceIsRenewedPerRequest(): void
    {
        $application = new Application(dirname(__DIR__));
        $application->router()->get('/synthetic', static fn(Request $request): Response => new Response('synthetic'));

        $first = $application->handle(new Request('GET', '/synthetic'))->headers()['Content-Security-Policy'];
        $second = $application->handle(new Request('GET', '/synthetic'))->headers()['Content-Security-Policy'];

        self::assertNotSame($first, $second);
    }

    public function testTemplatesAvoidInlineEventHandlersAndStyleAttributes(): void
    {
        $viewFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__) . '/views')
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $viewFiles[] = $file->getPathname();
            }
        }
        self::assertNotEmpty($viewFiles);

        foreach ($viewFiles as $file) {
            $contents = (string) file_get_contents($file);
            self::assertStringNotContainsString(' style="', $contents, $file);
            self::assertStringNotContainsString(" style='", $contents, $file);
            self::assertDoesNotMatchRegularExpression('/\son[a-z]+="/i', $contents, $file);
            self::assertStringNotContainsString('javascript:', $contents, $file);
        }
    }

    public function testPublicApachePolicyMatchesApplicationFrameAndXssPolicy(): void
    {
        $policy = file_get_contents(dirname(__DIR__) . '/public/.htaccess');
        self::assertIsString($policy);

        self::assertStringContainsString('Header always set X-Frame-Options "DENY"', $policy);
        self::assertStringContainsString('Header always set X-XSS-Protection "0"', $policy);
        self::assertStringNotContainsString('X-Frame-Options "SAMEORIGIN"', $policy);
        self::assertStringNotContainsString('X-XSS-Protection "1; mode=block"', $policy);
    }

    #[DataProvider('sensitiveApplicationPaths')]
    public function testCredentialAndTokenPagesCannotBeCached(string $path): void
    {
        $application = new Application(dirname(__DIR__));
        $application->router()->get($path, static fn(Request $request): Response => new Response('synthetic'));

        $response = $application->handle(new Request('GET', $path));

        self::assertSame('private, no-store, max-age=0', $response->headers()['Cache-Control']);
    }

    /** @return iterable<string, array{string}> */
    public static function sensitiveApplicationPaths(): iterable
    {
        yield 'club login' => ['/clubs/login'];
        yield 'admin login' => ['/admin/login'];
        yield 'registration' => ['/clubs/register'];
        yield 'forgot password' => ['/clubs/forgot-password'];
        yield 'reset password' => ['/clubs/reset-password'];
        yield 'registration confirmation' => ['/clubs/confirm-registration'];
    }

    #[DataProvider('tokenApplicationPaths')]
    public function testTokenPagesDoNotSendTheirUrlsAsReferrers(string $path): void
    {
        $application = new Application(dirname(__DIR__));
        $application->router()->get($path, static fn(Request $request): Response => new Response('synthetic'));

        $response = $application->handle(new Request('GET', $path . '?token=synthetic-token'));

        self::assertSame('no-referrer', $response->headers()['Referrer-Policy']);
    }

    /** @return iterable<string, array{string}> */
    public static function tokenApplicationPaths(): iterable
    {
        yield 'reset password' => ['/clubs/reset-password'];
        yield 'registration confirmation' => ['/clubs/confirm-registration'];
    }

    public function testHttpsResponsesIncludeTransportSecurity(): void
    {
        $application = new Application(dirname(__DIR__));
        $application->router()->get('/synthetic', static fn(Request $request): Response => new Response('synthetic'));

        $response = $application->handle(new Request(
            'GET',
            '/synthetic',
            [],
            [],
            ['HTTPS' => 'on']
        ));

        self::assertSame(
            'max-age=31536000; includeSubDomains',
            $response->headers()['Strict-Transport-Security']
        );
    }

    private function isForbiddenByRootPolicy(string $path): bool
    {
        return $this->isForbiddenByPolicy($this->rootPolicy(), $path);
    }

    private function isForbiddenByArtifactPolicy(string $path): bool
    {
        return $this->isForbiddenByPolicy($this->artifactPolicy(), $path);
    }

    private function artifactPolicy(): string
    {
        $policy = file_get_contents(dirname(__DIR__) . '/.htaccess');
        self::assertIsString($policy);

        return $policy;
    }

    private function isForbiddenByPolicy(string $policy, string $path): bool
    {
        preg_match_all(
            '/^RewriteRule\s+(\S+)\s+-\s+\[[^\]]*\bF\b[^\]]*\]$/m',
            $policy,
            $rules
        );

        foreach ($rules[1] as $pattern) {
            if (preg_match('#' . str_replace('#', '\\#', $pattern) . '#i', $path) === 1) {
                return true;
            }
        }

        return false;
    }

    private function rootPolicy(): string
    {
        $policy = file_get_contents(dirname(__DIR__) . '/root.htaccess');
        self::assertIsString($policy);

        return $policy;
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
