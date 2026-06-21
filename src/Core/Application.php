<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Application
{
    private Router $router;
    private View $view;

    public function __construct(private readonly string $basePath)
    {
        $this->view = new View($basePath . '/views');
        $this->router = new Router($this->view, Request::fromGlobals());
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
            return new Response(
                $this->view->render('errors/' . $exception->statusCode(), [
                    'title' => $exception->getMessage(),
                    'message' => $exception->getMessage(),
                ]),
                $exception->statusCode()
            );
        } catch (Throwable $exception) {
            if (config('app.debug', false)) {
                return new Response('<pre>' . e((string) $exception) . '</pre>', 500);
            }

            return new Response(
                $this->view->render('errors/500', [
                    'title' => 'Server error',
                    'message' => 'Something went wrong.',
                ]),
                500
            );
        }
    }

    public function basePath(): string
    {
        return $this->basePath;
    }
}
