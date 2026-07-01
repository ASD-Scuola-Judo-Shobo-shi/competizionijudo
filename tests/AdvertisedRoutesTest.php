<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class AdvertisedRoutesTest extends TestCase
{
    public function testEveryReadmeFeatureHasAnExplicitRoute(): void
    {
        $root = dirname(__DIR__);
        $readme = (string) file_get_contents($root . '/README.md');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $advertised = [
            '/' => 'get',
            '/events.php' => 'get',
            '/event_details.php' => 'get',
            '/event_entries.php' => 'get',
            '/privacy' => 'get',
            '/health' => 'get',
            '/language/switch' => 'get',
            '/club_register.php' => 'get',
            '/club_login.php' => 'get',
            '/club_area.php' => 'get',
            '/club_athletes_export.csv' => 'get',
            '/club_athletes_import.php' => 'post',
            '/event_register.php' => 'get',
            '/club_delete_athlete.php' => 'post',
            '/admin_manage_events.php' => 'get',
            '/admin_add_event.php' => 'get',
            '/admin_manage_clubs.php' => 'get',
            '/admin_edit_club.php' => 'get',
            '/admin_delete_event.php' => 'post',
            '/admin_delete_club.php' => 'post',
        ];

        foreach ($advertised as $path => $method) {
            self::assertStringContainsString('`' . $path . '`', $readme, $path);
            self::assertStringContainsString("\$router->{$method}('{$path}'", $routes, $path);
        }
    }
}
