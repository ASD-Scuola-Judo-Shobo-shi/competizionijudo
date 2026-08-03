<?php

declare(strict_types=1);

namespace App\Core;

use App\Security\SessionCredentialValidator;

final class Router
{
    /**
     * @var array<string, array<string, callable|array{0: class-string<Controller>, 1: string}>>
     */
    private array $routes = [];

    /** @var array<string, array<string, string>> */
    private array $policies = [];

    public function __construct(
        private readonly View $view,
        private readonly ?SessionCredentialValidator $sessionCredentialValidator = null
    ) {
    }

    /** @param callable|array{0: class-string<Controller>, 1: string} $handler */
    public function get(string $path, callable|array $handler, string $policy = AuthContext::PUBLIC): void
    {
        $this->add('GET', $path, $handler, $policy);
    }

    /** @param callable|array{0: class-string<Controller>, 1: string} $handler */
    public function post(string $path, callable|array $handler, string $policy = AuthContext::PUBLIC): void
    {
        $this->add('POST', $path, $handler, $policy);
    }

    public function dispatch(Request $request): Response
    {
        $this->validateSessionCredentials();
        $handler = $this->routes[$request->method()][$request->path()] ?? null;

        if ($handler === null) {
            $allowedMethods = $this->allowedMethods($request->path());
            if ($allowedMethods !== []) {
                throw new HttpException(
                    405,
                    __('errors.method_not_allowed'),
                    [
                        'Content-Type' => 'text/html; charset=UTF-8',
                        'Allow' => implode(', ', $allowedMethods),
                    ]
                );
            }

            throw new HttpException(404, __('errors.page_not_found'));
        }

        $policy = $this->policies[$request->method()][$request->path()] ?? AuthContext::PUBLIC;
        if (!AuthContext::permits($policy)) {
            return new Response('', 302, ['Location' => base_url(AuthContext::loginPath($policy))]);
        }

        if (is_array($handler)) {
            [$controller, $method] = $handler;
            $handler = [new $controller($this->view, $request), $method];
        }

        $response = $handler($request);

        if (!$response instanceof Response) {
            throw new HttpException(500, 'Invalid response');
        }

        return $response;
    }

    private function validateSessionCredentials(): void
    {
        if ($this->sessionCredentialValidator === null) {
            return;
        }

        $principal = AuthContext::principal();
        if (
            $principal !== null
            && !$this->sessionCredentialValidator->isCurrent(
                $principal,
                Session::credentialFingerprint()
            )
        ) {
            Session::invalidateAuthentication();
        }
    }

    /** @param callable|array{0: class-string<Controller>, 1: string} $handler */
    private function add(
        string $method,
        string $path,
        callable|array $handler,
        string $policy
    ): void {
        $normalizedPath = '/' . trim($path, '/');
        $this->routes[strtoupper($method)][$normalizedPath] = $handler;
        $this->policies[strtoupper($method)][$normalizedPath] = $policy;
    }

    /** @return list<string> */
    private function allowedMethods(string $path): array
    {
        $methods = [];
        foreach ($this->routes as $method => $routes) {
            if (isset($routes[$path])) {
                $methods[] = $method;
            }
        }
        sort($methods);

        return $methods;
    }
}
