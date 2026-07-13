<?php

declare(strict_types=1);

namespace Tests;

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
        Session::set('is_admin', true);
        Session::flash('message', 'stale');
        $oldToken = csrf_token();

        Session::authenticateClub(42);

        self::assertSame(42, Session::get('club_id'));
        self::assertFalse((bool) Session::get('is_admin'));
        self::assertNull(Session::pullFlash('message'));
        self::assertNotSame($oldToken, csrf_token());
        self::assertSame('club', Session::principal()?->type);
    }

    public function testAdministratorAuthenticationClearsClubAndRotatesCsrf(): void
    {
        Session::set('club_id', 42);
        $oldToken = csrf_token();

        Session::authenticateAdministrator();

        self::assertTrue((bool) Session::get('is_admin'));
        self::assertNull(Session::get('club_id'));
        self::assertNotSame($oldToken, csrf_token());
        self::assertSame('administrator', Session::principal()?->type);
    }
}
