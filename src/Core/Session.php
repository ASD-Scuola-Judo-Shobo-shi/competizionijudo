<?php

declare(strict_types=1);

namespace App\Core;

use LogicException;
use RuntimeException;

final class Session
{
    private const CONTEXT_KEY = '_session_context';

    private static ?SessionConfiguration $configuration = null;

    public static function configureFromEnvironment(): void
    {
        self::configure(SessionConfiguration::fromEnvironment());
    }

    public static function configure(SessionConfiguration $configuration): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            throw new LogicException('Cannot configure an active session.');
        }

        self::setIni('session.use_strict_mode', '1');
        self::setIni('session.use_only_cookies', '1');

        session_name($configuration->cookieName);
        if (session_name() !== $configuration->cookieName) {
            throw new RuntimeException('Unable to configure the session cookie name.');
        }

        if (
            !session_set_cookie_params([
            'lifetime' => 0,
            'path' => $configuration->cookiePath,
            'domain' => '',
            'secure' => self::isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
            ])
        ) {
            throw new RuntimeException('Unable to configure the session cookie parameters.');
        }

        self::$configuration = $configuration;
    }

    public static function configuration(): SessionConfiguration
    {
        if (self::$configuration === null) {
            self::configureFromEnvironment();
        }

        return self::$configuration;
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $submittedId = self::submittedSessionId();
            if (!session_start()) {
                throw new RuntimeException('Unable to start the session.');
            }

            self::verifyContext($submittedId);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();

        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::start();

        return isset($_SESSION[$key]);
    }

    public static function delete(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        self::start();
        $_SESSION['_flash'][$key] = $value;
    }

    public static function pullFlash(string $key, mixed $default = null): mixed
    {
        self::start();
        $flash = $_SESSION['_flash'] ?? [];
        if (!is_array($flash)) {
            unset($_SESSION['_flash']);

            return $default;
        }

        $value = $flash[$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        if (empty($_SESSION['_flash'])) {
            unset($_SESSION['_flash']);
        }

        return $value;
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $sessionName = session_name();
        $cookieParameters = session_get_cookie_params();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies') === '1') {
            if (!headers_sent()) {
                setcookie($sessionName, '', [
                    'expires' => time() - 42000,
                    'path' => $cookieParameters['path'],
                    'domain' => $cookieParameters['domain'],
                    'secure' => $cookieParameters['secure'],
                    'httponly' => $cookieParameters['httponly'],
                    'samesite' => $cookieParameters['samesite'],
                ]);
            }

            unset($_COOKIE[$sessionName]);
        }

        session_id('');
    }

    private static function verifyContext(string $submittedId): void
    {
        $configuration = self::configuration();
        $context = $_SESSION[self::CONTEXT_KEY] ?? null;
        if (is_string($context) && hash_equals($configuration->context, $context)) {
            return;
        }

        if ($submittedId !== '' && hash_equals($submittedId, session_id())) {
            self::replaceForeignSession();

            return;
        }

        $_SESSION[self::CONTEXT_KEY] = $configuration->context;
    }

    private static function replaceForeignSession(): void
    {
        $sessionName = session_name();
        session_write_close();
        session_id('');
        unset($_COOKIE[$sessionName]);

        if (!session_start()) {
            throw new RuntimeException('Unable to replace a session from another environment.');
        }

        $_SESSION = [self::CONTEXT_KEY => self::configuration()->context];
    }

    private static function submittedSessionId(): string
    {
        $sessionName = session_name();
        $cookieId = $_COOKIE[$sessionName] ?? null;
        if (is_string($cookieId) && $cookieId !== '') {
            return $cookieId;
        }

        return session_id();
    }

    private static function setIni(string $key, string $value): void
    {
        if (ini_set($key, $value) === false || ini_get($key) !== $value) {
            throw new RuntimeException(sprintf('Unable to enable required PHP session setting: %s.', $key));
        }
    }

    private static function isSecureRequest(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    }
}
