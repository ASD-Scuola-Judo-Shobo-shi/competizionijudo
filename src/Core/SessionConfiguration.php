<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

final class SessionConfiguration
{
    public readonly string $environment;
    public readonly string $cookieName;
    public readonly string $cookiePath;
    public readonly string $context;

    public function __construct(string $environment, string $cookiePath)
    {
        $this->environment = self::normalizeEnvironment($environment);
        $this->cookiePath = self::normalizeCookiePath($cookiePath);
        $this->cookieName = self::cookieName($this->environment, $this->cookiePath);
        $this->context = $this->environment . ':' . $this->cookiePath;
    }

    public static function fromEnvironment(): self
    {
        $routePrefix = $_SERVER['APP_ROUTE_PREFIX'] ?? null;
        if (is_string($routePrefix) && $routePrefix !== '') {
            $cookiePath = $routePrefix;
        } else {
            $appUrl = trim((string) \env('APP_URL', ''));
            $cookiePath = $appUrl === '' ? '/' : (parse_url($appUrl, PHP_URL_PATH) ?: '/');
        }

        return new self((string) \env('APP_ENV', 'production'), $cookiePath);
    }

    private static function normalizeEnvironment(string $environment): string
    {
        $environment = strtolower(trim($environment));
        if (preg_match('/\A[a-z][a-z0-9_-]{0,31}\z/', $environment) !== 1) {
            throw new InvalidArgumentException('APP_ENV must be a short lowercase environment identifier.');
        }

        return $environment;
    }

    private static function normalizeCookiePath(string $cookiePath): string
    {
        $cookiePath = trim($cookiePath);
        if ($cookiePath === '' || $cookiePath === '/') {
            return '/';
        }

        if (
            !str_starts_with($cookiePath, '/')
            || str_contains($cookiePath, '//')
            || str_contains($cookiePath, '..')
            || preg_match('#\A/[A-Za-z0-9._~/-]+\z#', $cookiePath) !== 1
        ) {
            throw new InvalidArgumentException('The session cookie path is invalid.');
        }

        return '/' . trim($cookiePath, '/');
    }

    private static function cookieName(string $environment, string $cookiePath): string
    {
        $pathIdentifier = trim(str_replace('/', '_', $cookiePath), '_');
        $pathIdentifier = $pathIdentifier === '' ? 'ROOT' : strtoupper($pathIdentifier);

        return 'CJ_' . strtoupper(str_replace('-', '_', $environment)) . '_' . $pathIdentifier . '_SESSION';
    }
}
