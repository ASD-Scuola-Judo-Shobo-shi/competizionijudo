<?php

declare(strict_types=1);

namespace Tests;

use App\Core\AuthContext;
use App\Core\Session;
use PHPUnit\Framework\TestCase;

final class SessionPrincipalTest extends TestCase
{
    protected function setUp(): void
    {
        Session::destroy();
        Session::start();
    }

    protected function tearDown(): void
    {
        Session::destroy();
    }

    public function testClubAuthenticationClearsAdministratorAndRotatesCsrf(): void
    {
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));
        Session::flash('message', 'stale');
        $oldToken = csrf_token();

        Session::authenticateClub(42, hash('sha256', 'test-club-credential'));

        self::assertSame(42, Session::get('club_id'));
        self::assertFalse((bool) Session::get('is_admin'));
        self::assertNull(Session::pullFlash('message'));
        self::assertNotSame($oldToken, csrf_token());
        self::assertSame('club', Session::principal()?->type);
    }

    public function testAdministratorAuthenticationClearsClubAndRotatesCsrf(): void
    {
        Session::authenticateClub(42, hash('sha256', 'test-club-credential'));
        $oldToken = csrf_token();

        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));

        self::assertTrue((bool) Session::get('is_admin'));
        self::assertNull(Session::get('club_id'));
        self::assertNotSame($oldToken, csrf_token());
        self::assertSame('administrator', Session::principal()?->type);
    }

    public function testLegacyPrivilegeFlagsNoLongerAuthorizeARequest(): void
    {
        Session::set('club_id', 42);
        Session::set('is_admin', true);

        self::assertNull(AuthContext::principal());
        self::assertFalse(AuthContext::isAuthenticated());
        self::assertFalse(AuthContext::isAdministrator());
        self::assertNull(AuthContext::clubId());
    }

    public function testIdleAuthenticationExpiresAndPreservesLocale(): void
    {
        Session::set('locale', 'en');
        Session::authenticateClub(42, hash('sha256', 'club-credential'));
        $oldSessionId = session_id();
        $_SESSION['_last_activity_at'] = time() - 1800;

        self::assertNull(Session::principal());
        self::assertNotSame($oldSessionId, session_id());
        self::assertSame('en', Session::get('locale'));
        self::assertNull(Session::get('principal'));
        self::assertNull(Session::credentialFingerprint());
    }

    public function testAbsoluteAuthenticationLifetimeExpiresDespiteRecentActivity(): void
    {
        Session::authenticateAdministrator(hash('sha256', 'administrator-credential'));
        $_SESSION['_authenticated_at'] = time() - 43200;
        $_SESSION['_last_activity_at'] = time();

        self::assertNull(Session::principal());
        self::assertFalse(AuthContext::isAuthenticated());
    }

    public function testValidAuthenticationRefreshesLastActivity(): void
    {
        $now = time();
        Session::authenticateClub(42, hash('sha256', 'club-credential'));
        $_SESSION['_authenticated_at'] = $now - 3600;
        $_SESSION['_last_activity_at'] = $now - 1200;

        self::assertSame('club', Session::principal()?->type);
        self::assertGreaterThanOrEqual($now, $_SESSION['_last_activity_at']);
    }
}
