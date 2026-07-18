<?php

declare(strict_types=1);

namespace App\Core;

/**
 * The only application-facing view of the authenticated session principal.
 */
final class AuthContext
{
    public const PUBLIC = 'public';
    public const AUTHENTICATED = 'authenticated';
    public const CLUB = 'club';
    public const ADMINISTRATOR = 'administrator';

    public static function principal(): ?SessionPrincipal
    {
        $principal = Session::principal();
        if ($principal !== null) {
            return $principal;
        }

        // Keep pre-typed sessions readable during the staged rollout. A mixed
        // legacy session is deliberately not authorized as either principal.
        $clubId = Session::get('club_id');
        $isAdministrator = Session::get('is_admin') === true;
        if ($isAdministrator || !is_int($clubId) || $clubId < 1) {
            return $isAdministrator && $clubId === null
                ? SessionPrincipal::administrator()
                : null;
        }

        return SessionPrincipal::club($clubId);
    }

    public static function isAdministrator(): bool
    {
        $principal = Session::principal();
        if ($principal !== null) {
            return $principal->type === self::ADMINISTRATOR;
        }

        // Legacy sessions could contain both keys. Preserve their existing
        // route behavior until the session-expiry rollout removes this bridge.
        return Session::get('is_admin') === true;
    }

    public static function clubId(): ?int
    {
        $principal = Session::principal();
        if ($principal !== null) {
            return $principal->type === self::CLUB ? $principal->clubId : null;
        }

        $clubId = Session::get('club_id');

        return is_int($clubId) && $clubId > 0 ? $clubId : null;
    }

    public static function isAuthenticated(): bool
    {
        return self::isAdministrator() || self::clubId() !== null;
    }

    public static function permits(string $policy): bool
    {
        return match ($policy) {
            self::PUBLIC => true,
            self::AUTHENTICATED => self::isAuthenticated(),
            self::CLUB => self::clubId() !== null,
            self::ADMINISTRATOR => self::isAdministrator(),
            default => throw new \InvalidArgumentException('Unknown route authorization policy.'),
        };
    }

    public static function loginPath(string $policy): string
    {
        return $policy === self::ADMINISTRATOR ? "/admin/login" : "/clubs/login";
    }
}
