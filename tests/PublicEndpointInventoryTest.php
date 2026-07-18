<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class PublicEndpointInventoryTest extends TestCase
{
    public function testEveryPublicPhpFileHasAnIntentionalRole(): void
    {
        $publicDirectory = dirname(__DIR__) . '/public';
        $files = array_map('basename', glob($publicDirectory . '/*.php') ?: []);
        sort($files);
        $expected = [
            'admin.php',
            'admin_edit_event.php',
            'admin_login.php',
            'club_forgot_password.php',
            'club_reset_password.php',
            'event.php',
            'event_details.php',
            'event_register.php',
            'event_show.php',
            'events.php',
            'index.php',
        ];

        self::assertSame($expected, $files);
    }

    public function testRouteWrappersAndCompatibilityRedirectsPointToSupportedEndpoints(): void
    {
        $root = dirname(__DIR__);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $routeWrappers = [
            '/admin',
            '/admin/events/edit',
            '/admin/login',
            '/clubs/forgot-password',
            '/clubs/reset-password',
            '/events/details',
            '/events/register',
            '/events',
        ];

        foreach ($routeWrappers as $path) {
            self::assertStringContainsString("$path'", $routes, $path);
        }

        foreach (['event.php', 'event_show.php'] as $file) {
            $contents = (string) file_get_contents($root . '/public/' . $file);
            self::assertStringContainsString("require dirname(__DIR__) . '/src/bootstrap.php';", $contents);
            self::assertStringContainsString("header('Location: ' . base_url('/events/details'));", $contents);
            self::assertStringContainsString('exit;', $contents);
        }
    }

    public function testReadmeDoesNotClaimUnsupportedExports(): void
    {
        $readme = (string) file_get_contents(dirname(__DIR__) . '/README.md');

        self::assertStringNotContainsString('CSV and Excel exports', $readme);
    }
}
