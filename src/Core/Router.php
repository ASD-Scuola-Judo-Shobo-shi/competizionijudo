<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /**
     * @var array<string, array<string, callable|array{0: class-string<Controller>, 1: string}>>
     */
    private array $routes = [];

    public function __construct(private readonly View $view)
    {
    }

    /** @param callable|array{0: class-string<Controller>, 1: string} $handler */
    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    /** @param callable|array{0: class-string<Controller>, 1: string} $handler */
    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(Request $request): Response
    {
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

    /** @param callable|array{0: class-string<Controller>, 1: string} $handler */
    private function add(string $method, string $path, callable|array $handler): void
    {
        $normalizedPath = '/' . trim($path, '/');
        $this->routes[strtoupper($method)][$normalizedPath] = $handler;
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
