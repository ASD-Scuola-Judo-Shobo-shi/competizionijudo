<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\MigrationWebhookController;
use App\Core\Logger;
use App\Core\Request;
use App\Core\View;
use App\Model\MigrationException;
use App\Service\AutomaticMigrationSafety;
use App\Service\MigrationExecutor;
use App\Service\MigrationWebhookAuthenticator;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class MigrationWebhookAuthenticatorTest extends TestCase
{
    public function testAcceptsOnlyFreshSignedPostRequests(): void
    {
        $secret = 'synthetic-webhook-secret';
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', MigrationWebhookAuthenticator::signingPayload($timestamp), $secret);
        $request = $this->signedRequest($timestamp, $signature);

        self::assertTrue((new MigrationWebhookAuthenticator($secret))->accepts($request, (int) $timestamp));
        self::assertTrue((new MigrationWebhookAuthenticator($secret))->accepts($request));
        self::assertFalse((new MigrationWebhookAuthenticator('different-secret'))->accepts($request, (int) $timestamp));
        self::assertFalse((new MigrationWebhookAuthenticator($secret))->accepts($request, (int) $timestamp + 301));
        self::assertFalse((new MigrationWebhookAuthenticator($secret))->accepts(
            new Request('POST', '/migrations'),
            (int) $timestamp
        ));
        self::assertFalse((new MigrationWebhookAuthenticator($secret))->accepts(
            new Request('POST', '/migrations', [], [], ['HTTP_X_MIGRATION_TIMESTAMP' => $timestamp]),
            (int) $timestamp
        ));
    }

    public function testWebhookRunsSignedServerLocalMigration(): void
    {
        $secret = 'synthetic-webhook-secret';
        $timestamp = (string) time();
        $safety = $this->createMock(AutomaticMigrationSafety::class);
        $safety->expects(self::once())->method('assertSafe');
        $executor = $this->createMock(MigrationExecutor::class);
        $executor->expects(self::once())->method('run');

        $response = $this->controller($safety, $executor)->run($this->signedRequest(
            $timestamp,
            hash_hmac('sha256', MigrationWebhookAuthenticator::signingPayload($timestamp), $secret)
        ));

        self::assertSame(200, $response->status());
        self::assertSame('{"status":"ok"}', $response->content());
    }

    public function testWebhookReportsMigrationFailureOnlyToASignedCaller(): void
    {
        $secret = 'synthetic-webhook-secret';
        $timestamp = (string) time();
        $failure = new MigrationException('20260716_000001_safe.sql', new RuntimeException('synthetic failure'));
        $safety = $this->createMock(AutomaticMigrationSafety::class);
        $safety->expects(self::once())->method('assertSafe');
        $executor = $this->createMock(MigrationExecutor::class);
        $executor->expects(self::once())->method('run')->willThrowException($failure);
        $executor->expects(self::once())->method('failureDetail')->with($failure)->willReturn('redacted diagnostic');
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())->method('error');

        $response = $this->controller($safety, $executor, $logger)->run($this->signedRequest(
            $timestamp,
            hash_hmac('sha256', MigrationWebhookAuthenticator::signingPayload($timestamp), $secret)
        ));

        self::assertSame(500, $response->status());
        self::assertSame(
            '{"status":"failed","migration":"20260716_000001_safe.sql","diagnostic":"redacted diagnostic"}',
            $response->content()
        );
    }

    public function testWebhookReportsUnexpectedFailureOnlyToASignedCaller(): void
    {
        $secret = 'synthetic-webhook-secret';
        $timestamp = (string) time();
        $failure = new RuntimeException('synthetic failure');
        $safety = $this->createMock(AutomaticMigrationSafety::class);
        $safety->expects(self::once())->method('assertSafe')->willThrowException($failure);
        $executor = $this->createMock(MigrationExecutor::class);
        $executor->expects(self::never())->method('run');
        $executor->expects(self::once())->method('failureDetail')->with($failure)->willReturn('redacted diagnostic');
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())->method('error');

        $response = $this->controller($safety, $executor, $logger)->run($this->signedRequest(
            $timestamp,
            hash_hmac('sha256', MigrationWebhookAuthenticator::signingPayload($timestamp), $secret)
        ));

        self::assertSame(500, $response->status());
        self::assertSame('{"status":"failed","diagnostic":"redacted diagnostic"}', $response->content());
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

    private function controller(
        AutomaticMigrationSafety $safety,
        MigrationExecutor $executor,
        ?Logger $logger = null
    ): MigrationWebhookController {
        $_ENV['MIGRATION_WEBHOOK_SECRET'] = 'synthetic-webhook-secret';
        $_SERVER['MIGRATION_WEBHOOK_SECRET'] = 'synthetic-webhook-secret';

        return new MigrationWebhookController(
            new View(dirname(__DIR__) . '/views'),
            new Request('POST', '/migrations'),
            $safety,
            $executor,
            $logger
        );
    }

    private function signedRequest(string $timestamp, string $signature): Request
    {
        return new Request('POST', '/migrations', [], [], [
            'HTTP_X_MIGRATION_TIMESTAMP' => $timestamp,
            'HTTP_X_MIGRATION_SIGNATURE' => $signature,
        ]);
    }
}
