<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\MigrationWebhookController;
use App\Core\Request;
use App\Core\View;
use App\Service\MigrationWebhookAuthenticator;
use PHPUnit\Framework\TestCase;

final class MigrationWebhookAuthenticatorTest extends TestCase
{
    public function testAcceptsOnlyFreshSignedPostRequests(): void
    {
        $secret = 'synthetic-webhook-secret';
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', MigrationWebhookAuthenticator::signingPayload($timestamp), $secret);
        $request = new Request('POST', '/migrations', [], [], [
            'HTTP_X_MIGRATION_TIMESTAMP' => $timestamp,
            'HTTP_X_MIGRATION_SIGNATURE' => $signature,
        ]);

        self::assertTrue((new MigrationWebhookAuthenticator($secret))->accepts($request, (int) $timestamp));
        self::assertFalse((new MigrationWebhookAuthenticator('different-secret'))->accepts($request, (int) $timestamp));
        self::assertFalse((new MigrationWebhookAuthenticator($secret))->accepts($request, (int) $timestamp + 301));
    }

    public function testWebhookControllerHidesTheRouteWhenSignatureIsMissing(): void
    {
        $_ENV['MIGRATION_WEBHOOK_SECRET'] = 'synthetic-webhook-secret';
        $_SERVER['MIGRATION_WEBHOOK_SECRET'] = 'synthetic-webhook-secret';

        try {
            $response = (new MigrationWebhookController(
                new View(dirname(__DIR__) . '/views'),
                new Request('POST', '/migrations')
            ))->run(new Request('POST', '/migrations'));

            self::assertSame(404, $response->status());
            self::assertSame('{"status":"not_found"}', $response->content());
            self::assertSame('no-store', $response->headers()['Cache-Control']);
        } finally {
            unset($_ENV['MIGRATION_WEBHOOK_SECRET'], $_SERVER['MIGRATION_WEBHOOK_SECRET']);
        }
    }

    public function testGitHubTriggerUsesTheSameSignedRequestProtocol(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__) . '/scripts/trigger-server-migrations.sh');

        self::assertStringContainsString('competizionijudo-migration-v1|', $script);
        self::assertStringContainsString('openssl dgst -sha256 -hmac "$MIGRATION_WEBHOOK_SECRET"', $script);
        self::assertStringContainsString('X-Migration-Timestamp:', $script);
        self::assertStringContainsString('X-Migration-Signature:', $script);
        self::assertStringContainsString('--fail-with-body', $script);
    }
}
