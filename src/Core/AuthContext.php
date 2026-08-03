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
        return Session::principal();
    }

    public static function isAdministrator(): bool
    {
        return Session::principal()?->type === self::ADMINISTRATOR;
    }

    public static function clubId(): ?int
    {
        $principal = Session::principal();

        return $principal?->type === self::CLUB ? $principal->clubId : null;
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
        return $policy === self::ADMINISTRATOR ? '/admin/login' : '/clubs/login';
    }
}
