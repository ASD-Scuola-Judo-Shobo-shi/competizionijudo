<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Application
{
    private Router $router;
    private View $view;
    private Logger $logger;

    public function __construct(private readonly string $basePath, ?Logger $logger = null)
    {
        $this->view = new View($basePath . '/views');
        $this->router = new Router($this->view, Request::fromGlobals());
        $this->logger = $logger ?? FileLogger::application();
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (HttpException $exception) {
            if ($exception->statusCode() >= 500) {
                $this->logFailure('application.http_failure', $exception, $request);

                return $this->serverError($request);
            }

            return new Response(
                $this->view->render('errors/' . $exception->statusCode(), [
                    'title' => $exception->getMessage(),
                    'message' => $exception->getMessage(),
                ]),
                $exception->statusCode()
            );
        } catch (Throwable $exception) {
            $this->logFailure('application.unhandled_failure', $exception, $request);

            return $this->serverError($request);
        }
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    private function logFailure(string $event, Throwable $exception, Request $request): void
    {
        $this->logger->error($event, $exception, $request->correlationId(), [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => 500,
        ]);
    }

    private function serverError(Request $request): Response
    {
        return new Response(
            $this->view->render('errors/500', [
                'title' => __('errors.server_error'),
                'message' => __('errors.unexpected_failure'),
                'reference' => __('errors.reference', ['id' => $request->correlationId()]),
            ], 'layouts/error'),
            500
        );
    }
}
