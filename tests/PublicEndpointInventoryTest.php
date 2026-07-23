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
        $expected = ['index.php'];

        self::assertSame($expected, $files);
    }

    public function testReadmeDoesNotClaimUnsupportedExports(): void
    {
        $readme = (string) file_get_contents(dirname(__DIR__) . '/README.md');

        self::assertStringNotContainsString('CSV and Excel exports', $readme);
    }
}
