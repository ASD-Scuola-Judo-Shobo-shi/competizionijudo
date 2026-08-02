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
            '/events' => 'get',
            '/events/details' => 'get',
            '/events/entries' => 'get',
            '/privacy' => 'get',
            '/health' => 'get',
            '/language/switch' => 'get',
            '/clubs/register' => 'get',
            '/clubs/login' => 'get',
            '/clubs/area' => 'get',
            '/clubs/athletes-export' => 'get',
            '/clubs/athletes-import' => 'post',
            '/events/register' => 'get',
            '/clubs/delete-athlete' => 'post',
            '/admin/events' => 'get',
            '/admin/events/details' => 'get',
            '/admin/events/export' => 'get',
            '/admin/clubs' => 'get',
            '/admin/clubs/edit' => 'get',
            '/admin/clubs/athletes' => 'get',
            '/admin/clubs/athletes/export' => 'get',
            '/admin/maintenance/athlete-duplicates' => 'get',
            '/admin/events/delete' => 'post',
            '/admin/clubs/delete' => 'post',
        ];

        foreach ($advertised as $path => $method) {
            self::assertStringContainsString('`' . $path . '`', $readme, $path);
            self::assertStringContainsString("\$router->{$method}('{$path}'", $routes, $path);
        }

        self::assertStringContainsString(
            "\$router->post('/admin/maintenance/athlete-duplicates'",
            $routes
        );
        self::assertStringContainsString("\$router->post('/admin/events/details'", $routes);
        self::assertStringNotContainsString('/admin/events/add', $routes);
    }
}
