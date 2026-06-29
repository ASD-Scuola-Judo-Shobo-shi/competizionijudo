<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

abstract class Controller
{
    protected readonly Logger $logger;

    public function __construct(
        protected readonly View $view,
        protected readonly Request $request,
        ?Logger $logger = null
    ) {
        $this->logger = $logger ?? FileLogger::application();
    }

    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        $data['currentPath'] = $this->request->path();

        return new Response($this->view->render($template, $data), $status);
    }

    protected function redirect(string $to, int $status = 302): Response
    {
        return new Response('', $status, ['Location' => $to]);
    }

    protected function reportFailure(string $event, Throwable $exception, Request $request): void
    {
        $this->logger->error($event, $exception, $request->correlationId(), [
            'method' => $request->method(),
            'path' => $request->path(),
        ]);
    }
}
