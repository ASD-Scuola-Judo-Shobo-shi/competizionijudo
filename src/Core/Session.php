<?php

declare(strict_types=1);

namespace App\Core;

use LogicException;
use RuntimeException;

final class Session
{
    private const CONTEXT_KEY = '_session_context';
    private const CONTEXT_VERSION = 'v3';
    private const AUTHENTICATED_AT_KEY = '_authenticated_at';
    private const LAST_ACTIVITY_AT_KEY = '_last_activity_at';
    private const CREDENTIAL_FINGERPRINT_KEY = '_credential_fingerprint';
    private const IDLE_TIMEOUT_SECONDS = 1800;
    private const ABSOLUTE_TIMEOUT_SECONDS = 43200;

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

    public static function authenticateClub(int $clubId, string $credentialFingerprint): void
    {
        self::authenticate(SessionPrincipal::club($clubId), $credentialFingerprint);
    }

    public static function authenticateAdministrator(string $credentialFingerprint): void
    {
        self::authenticate(SessionPrincipal::administrator(), $credentialFingerprint);
    }

    public static function principal(): ?SessionPrincipal
    {
        self::start();
        $principal = $_SESSION['principal'] ?? null;
        if (!is_array($principal) || !isset($principal['type']) || !is_string($principal['type'])) {
            return null;
        }

        $resolved = match ($principal['type']) {
            'administrator' => SessionPrincipal::administrator(),
            'club' => isset($principal['club_id'])
                && is_int($principal['club_id'])
                && $principal['club_id'] > 0
                ? SessionPrincipal::club($principal['club_id'])
                : null,
            default => null,
        };

        if ($resolved === null || !self::authenticationLifetimeIsValid(time())) {
            self::invalidateAuthentication();

            return null;
        }

        $_SESSION[self::LAST_ACTIVITY_AT_KEY] = time();

        return $resolved;
    }

    public static function credentialFingerprint(): ?string
    {
        self::start();
        $fingerprint = $_SESSION[self::CREDENTIAL_FINGERPRINT_KEY] ?? null;

        return is_string($fingerprint) && preg_match('/\A[a-f0-9]{64}\z/', $fingerprint) === 1
            ? $fingerprint
            : null;
    }

    public static function invalidateAuthentication(): void
    {
        self::start();
        $locale = $_SESSION['locale'] ?? null;
        session_regenerate_id(true);
        $_SESSION = [
            self::CONTEXT_KEY => self::context(),
            'csrf_token' => bin2hex(random_bytes(32)),
        ];
        if (is_string($locale) && in_array($locale, ['it', 'en'], true)) {
            $_SESSION['locale'] = $locale;
        }
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
        if (is_string($context) && hash_equals(self::context(), $context)) {
            return;
        }

        if ($submittedId !== '' && hash_equals($submittedId, session_id())) {
            self::replaceForeignSession();

            return;
        }

        $_SESSION[self::CONTEXT_KEY] = self::context();
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

        $_SESSION = [self::CONTEXT_KEY => self::context()];
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

    private static function authenticate(
        SessionPrincipal $principal,
        string $credentialFingerprint
    ): void {
        self::start();
        if (preg_match('/\A[a-f0-9]{64}\z/', $credentialFingerprint) !== 1) {
            throw new \InvalidArgumentException('A credential fingerprint must be a SHA-256 digest.');
        }

        $locale = $_SESSION['locale'] ?? null;
        $now = time();
        session_regenerate_id(true);
        $_SESSION = [
            self::CONTEXT_KEY => self::context(),
            'principal' => $principal->type === 'club'
                ? ['type' => 'club', 'club_id' => $principal->clubId]
                : ['type' => 'administrator'],
            'csrf_token' => bin2hex(random_bytes(32)),
            self::AUTHENTICATED_AT_KEY => $now,
            self::LAST_ACTIVITY_AT_KEY => $now,
        ];
        $_SESSION[self::CREDENTIAL_FINGERPRINT_KEY] = $credentialFingerprint;
        if (is_string($locale) && in_array($locale, ['it', 'en'], true)) {
            $_SESSION['locale'] = $locale;
        }
        if ($principal->type === 'club') {
            $_SESSION['club_id'] = $principal->clubId;
        } else {
            $_SESSION['is_admin'] = true;
        }
    }

    private static function authenticationLifetimeIsValid(int $now): bool
    {
        $authenticatedAt = $_SESSION[self::AUTHENTICATED_AT_KEY] ?? null;
        $lastActivityAt = $_SESSION[self::LAST_ACTIVITY_AT_KEY] ?? null;
        if (!is_int($authenticatedAt) || !is_int($lastActivityAt)) {
            return false;
        }

        if ($authenticatedAt > $lastActivityAt || $lastActivityAt > $now) {
            return false;
        }

        return $now - $authenticatedAt < self::ABSOLUTE_TIMEOUT_SECONDS
            && $now - $lastActivityAt < self::IDLE_TIMEOUT_SECONDS;
    }

    private static function context(): string
    {
        return self::CONTEXT_VERSION . ':' . self::configuration()->context;
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
