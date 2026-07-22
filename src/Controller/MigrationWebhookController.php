<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Model\MigrationException;
use App\Service\MigrationBasicAuthenticator;
use App\Service\MigrationExecutor;

final class MigrationWebhookController extends Controller
{
    private readonly MigrationBasicAuthenticator $authenticator;
    private readonly MigrationExecutor $executor;

    public function __construct(
        View $view,
        Request $request,
        ?MigrationBasicAuthenticator $authenticator = null,
        ?MigrationExecutor $executor = null,
        ?Logger $logger = null
    ) {
        parent::__construct($view, $request, $logger);
        $this->authenticator = $authenticator ?? new MigrationBasicAuthenticator(
            trim((string) env('ADMIN_USER', ''))
        );
        $this->executor = $executor ?? new MigrationExecutor();
    }

    public function run(Request $request): Response
    {
        if (!$this->authenticator->accepts($request)) {
            return $this->json(
                ['status' => 'unauthorized'],
                401,
                ['WWW-Authenticate' => 'Basic realm="Migration endpoint", charset="UTF-8"']
            );
        }

        try {
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

    /**
     * @param array<string, string> $data
     * @param array<string, string> $headers
     */
    private function json(array $data, int $status = 200, array $headers = []): Response
    {
        return new Response(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $status,
            array_merge([
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ], $headers)
        );
    }
}
