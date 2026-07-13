<?php

declare(strict_types=1);

namespace App\Core;

use App\Presentation\LayoutContext;
use Throwable;

final class Application
{
    private Router $router;
    private View $view;
    private Logger $logger;

    public function __construct(private readonly string $basePath, ?Logger $logger = null)
    {
        $this->view = new View($basePath . '/views');
        $this->router = new Router($this->view);
        $this->logger = $logger ?? FileLogger::application();
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->secure($this->router->dispatch($request));
        } catch (HttpException $exception) {
            if ($exception->statusCode() >= 500) {
                $this->logFailure('application.http_failure', $exception, $request);

                return $this->serverError($request);
            }

            $data = array_merge([
                    'title' => $exception->getMessage(),
                    'message' => $exception->getMessage(),
                ], LayoutContext::build($request));

            return $this->secure(new Response(
                $this->view->render('errors/' . $exception->statusCode(), $data),
                $exception->statusCode(),
                $exception->headers()
            ));
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
        return $this->secure(new Response(
            $this->view->render('errors/500', [
                'title' => __('errors.server_error'),
                'message' => __('errors.unexpected_failure'),
                'reference' => __('errors.reference', ['id' => $request->correlationId()]),
            ], 'layouts/error'),
            500
        ));
    }

    private function secure(Response $response): Response
    {
        $headers = [
            'Content-Security-Policy-Report-Only' => "default-src 'self'; base-uri 'self'; frame-ancestors 'self'; object-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; form-action 'self'",
            'Permissions-Policy' => 'camera=(), geolocation=(), microphone=()',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
        ];
        if (session_status() === PHP_SESSION_ACTIVE && AuthContext::isAuthenticated()) {
            $headers['Cache-Control'] = 'private, no-store, max-age=0';
        }

        return $response->withHeaders($headers);
    }
}
