<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Service\DeploymentHealth;

final class HealthController extends Controller
{
    private readonly DeploymentHealth $health;

    public function __construct(
        View $view,
        Request $request,
        ?DeploymentHealth $health = null,
        ?Logger $logger = null
    ) {
        parent::__construct($view, $request, $logger);
        $this->health = $health ?? new DeploymentHealth();
    }

    public function show(Request $request): Response
    {
        try {
            return $this->json([
                'status' => 'ok',
                'revision' => $this->health->verify(),
            ]);
        } catch (\Throwable $exception) {
            $this->reportFailure('health.check_failed', $exception, $request);

            return $this->json(['status' => 'unavailable'], 503);
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
