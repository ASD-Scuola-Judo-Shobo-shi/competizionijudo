<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
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
}
