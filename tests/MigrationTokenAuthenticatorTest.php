<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\MigrationWebhookController;
use App\Core\Logger;
use App\Core\Request;
use App\Core\View;
use App\Model\MigrationException;
use App\Service\MigrationExecutor;
use App\Service\MigrationTokenAuthenticator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MigrationTokenAuthenticatorTest extends TestCase
{
    public function testAcceptsOnlyTheConfiguredMigrationToken(): void
    {
        $authenticator = new MigrationTokenAuthenticator('migration-token');

        self::assertTrue($authenticator->accepts($this->tokenRequest('migration-token')));
        self::assertFalse($authenticator->accepts($this->tokenRequest('different-token')));
        self::assertFalse($authenticator->accepts(new Request('POST', '/migrations')));
        self::assertFalse((new MigrationTokenAuthenticator(''))->accepts($this->tokenRequest('migration-token')));
    }

    public function testEndpointRunsAnAuthenticatedServerLocalMigration(): void
    {
        $executor = $this->createMock(MigrationExecutor::class);
        $executor->expects(self::once())->method('run');

        $response = $this->controller(new MigrationTokenAuthenticator('migration-token'), $executor)
            ->run($this->tokenRequest('migration-token'));

        self::assertSame(200, $response->status());
        self::assertSame('{"status":"ok"}', $response->content());
    }

    public function testEndpointReportsMigrationFailureOnlyToAnAuthenticatedCaller(): void
    {
        $failure = new MigrationException('20260716_000001_safe.sql', new RuntimeException('synthetic failure'));
        $executor = $this->createMock(MigrationExecutor::class);
        $executor->expects(self::once())->method('run')->willThrowException($failure);
        $executor->expects(self::once())->method('failureDetail')->with($failure)->willReturn('redacted diagnostic');
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())->method('error');

        $response = $this->controller(new MigrationTokenAuthenticator('migration-token'), $executor, $logger)
            ->run($this->tokenRequest('migration-token'));

        self::assertSame(500, $response->status());
        self::assertSame(
            '{"status":"failed","migration":"20260716_000001_safe.sql","diagnostic":"redacted diagnostic"}',
            $response->content()
        );
    }

    public function testEndpointRejectsAnUnauthenticatedCaller(): void
    {
        $executor = $this->createMock(MigrationExecutor::class);
        $executor->expects(self::never())->method('run');

        $response = $this->controller(new MigrationTokenAuthenticator('migration-token'), $executor)
            ->run($this->tokenRequest('different-token'));

        self::assertSame(401, $response->status());
        self::assertSame('{"status":"unauthorized"}', $response->content());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
    }

    public function testGitHubTriggerUsesTheMigrationTokenHeader(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__) . '/scripts/trigger-server-migrations.sh');

        self::assertStringContainsString('MIGRATIONS_TOKEN is required', $script);
        self::assertStringContainsString('--header "X-Migration-Token: ${MIGRATIONS_TOKEN}"', $script);
        self::assertStringContainsString('--fail-with-body', $script);
        self::assertStringContainsString("--write-out '%{http_code}'", $script);
        self::assertStringContainsString('Migration endpoint returned unexpected HTTP', $script);
        self::assertStringNotContainsString('MIGRATION_BASIC_AUTH_USER', $script);
        self::assertStringNotContainsString('--basic', $script);
    }

    public function testGitHubTriggerAcceptsAnHttpsEndpointWithAPath(): void
    {
        $directory = sys_get_temp_dir() . '/competizionijudo-fake-curl-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        $fakeCurl = $directory . '/curl';
        self::assertNotFalse(file_put_contents(
            $fakeCurl,
            <<<'BASH'
#!/usr/bin/env bash
output=''
while (( $# > 0 )); do
  case "$1" in
    --output)
      output="$2"
      shift 2
      ;;
    --write-out)
      shift 2
      ;;
    *)
      shift
      ;;
  esac
done
printf '%s' '{"status":"ok"}' > "$output"
printf '%s' '200'
BASH
        ));
        self::assertTrue(chmod($fakeCurl, 0700));

        try {
            $process = proc_open(
                ['bash', dirname(__DIR__) . '/scripts/trigger-server-migrations.sh'],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                dirname(__DIR__),
                [
                    'PATH' => $directory . ':' . (string) getenv('PATH'),
                    'MIGRATION_ENDPOINT_URL' => 'https://www.competizionijudo.it/prod/migrations/',
                    'MIGRATIONS_TOKEN' => 'synthetic-migration-token',
                ]
            );
            self::assertIsResource($process);

            $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            self::assertSame(0, proc_close($process), $output);
        } finally {
            unlink($fakeCurl);
            rmdir($directory);
        }
    }

    private function controller(
        MigrationTokenAuthenticator $authenticator,
        MigrationExecutor $executor,
        ?Logger $logger = null
    ): MigrationWebhookController {
        return new MigrationWebhookController(
            new View(dirname(__DIR__) . '/views'),
            new Request('POST', '/migrations'),
            $authenticator,
            $executor,
            $logger
        );
    }

    private function tokenRequest(string $token): Request
    {
        return new Request('POST', '/migrations', [], [], [
            'HTTP_X_MIGRATION_TOKEN' => $token,
        ]);
    }
}
