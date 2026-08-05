<?php

declare(strict_types=1);

namespace App\Core;

use App\Presentation\LayoutContext;
use App\Security\DatabaseSessionCredentialValidator;
use App\Security\SessionCredentialValidator;
use Throwable;

final class Application
{
    /** @var list<string> */
    private const SENSITIVE_PATHS = [
        '/admin/login',
        '/clubs/confirm-registration',
        '/clubs/forgot-password',
        '/clubs/login',
        '/clubs/register',
        '/clubs/reset-password',
    ];

    /** @var list<string> */
    private const TOKEN_PATHS = [
        '/clubs/confirm-registration',
        '/clubs/reset-password',
    ];

    private Router $router;
    private View $view;
    private Logger $logger;
    private string $cspNonce = '';

    public function __construct(
        private readonly string $basePath,
        ?Logger $logger = null,
        ?SessionCredentialValidator $sessionCredentialValidator = null
    ) {
        $this->view = new View($basePath . '/views');
        $this->router = new Router(
            $this->view,
            $sessionCredentialValidator ?? new DatabaseSessionCredentialValidator()
        );
        $this->logger = $logger ?? FileLogger::application();
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function handle(Request $request): Response
    {
        $this->cspNonce = bin2hex(random_bytes(16));
        $this->view->setCspNonce($this->cspNonce);

        try {
            return $this->secure($this->router->dispatch($request), $request);
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
            ), $request);
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
        ), $request);
    }

    private function secure(Response $response, Request $request): Response
    {
        $headers = [
            'Content-Security-Policy' => "default-src 'self'; base-uri 'self'; frame-ancestors 'none'; object-src 'none'; script-src 'self' 'nonce-" . $this->cspNonce . "'; style-src 'self' 'nonce-" . $this->cspNonce . "'; img-src 'self' data:; font-src 'self'; form-action 'self'",
            'Permissions-Policy' => 'camera=(), geolocation=(), microphone=()',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '0',
        ];
        if ($this->isSecureRequest($request)) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        if (
            $request->method() !== 'GET'
            || in_array($request->path(), self::SENSITIVE_PATHS, true)
            || (session_status() === PHP_SESSION_ACTIVE && AuthContext::isAuthenticated())
        ) {
            $headers['Cache-Control'] = 'private, no-store, max-age=0';
        }
        if (in_array($request->path(), self::TOKEN_PATHS, true)) {
            $headers['Referrer-Policy'] = 'no-referrer';
        }

        return $response->withHeaders($headers);
    }

    private function isSecureRequest(Request $request): bool
    {
        return (
            trim((string) $request->server('HTTPS', '')) !== ''
            && strtolower((string) $request->server('HTTPS')) !== 'off'
        ) || (int) $request->server('SERVER_PORT', 0) === 443;
    }
}
