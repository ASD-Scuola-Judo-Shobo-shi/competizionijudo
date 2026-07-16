<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Model\MigrationException;
use App\Service\AutomaticMigrationSafety;
use App\Service\MigrationExecutor;
use App\Service\MigrationWebhookAuthenticator;

final class MigrationWebhookController extends Controller
{
    private readonly AutomaticMigrationSafety $safety;
    private readonly MigrationExecutor $executor;

    public function __construct(
        View $view,
        Request $request,
        ?AutomaticMigrationSafety $safety = null,
        ?MigrationExecutor $executor = null,
        ?Logger $logger = null
    ) {
        parent::__construct($view, $request, $logger);
        $this->safety = $safety ?? new AutomaticMigrationSafety();
        $this->executor = $executor ?? new MigrationExecutor();
    }

    public function run(Request $request): Response
    {
        $secret = trim((string) env('MIGRATION_WEBHOOK_SECRET', ''));
        if ($secret === '' || !(new MigrationWebhookAuthenticator($secret))->accepts($request)) {
            return $this->json(['status' => 'not_found'], 404);
        }

        try {
            $this->safety->assertSafe();
            $this->executor->run();

            return $this->json(['status' => 'ok']);
        } catch (MigrationException $exception) {
            $this->reportFailure('migration.webhook_failed', $exception, $request);

            return $this->json([
                'status' => 'failed',
                'migration' => $exception->version(),
                'diagnostic' => $this->executor->failureDetail($exception),
            ], 500);
        } catch (\Throwable $exception) {
            $this->reportFailure('migration.webhook_failed', $exception, $request);

            return $this->json([
                'status' => 'failed',
                'diagnostic' => $this->executor->failureDetail($exception),
            ], 500);
        }
    }

    /** @param array<string, string> $data */
    private function json(array $data, int $status = 200): Response
    {
        return new Response(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $status,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]
        );
    }
}
