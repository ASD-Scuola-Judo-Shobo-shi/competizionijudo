<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    public function __construct(private readonly string $basePath)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], string $layout = 'layouts/app'): string
    {
        $data = array_merge([
            'favicon' => (string) config('app.favicon'),
        ], $data);
        $content = $this->renderPartial($template, $data);

        return $this->renderPartial($layout, array_merge($data, ['content' => $content]));
    }

    /** @param array<string, mixed> $data */
    private function renderPartial(string $template, array $data = []): string
    {
        $path = $this->basePath . '/' . trim($template, '/') . '.php';

        if (!is_file($path)) {
            throw new RuntimeException(sprintf('View "%s" not found.', $template));
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $path;

        return (string) ob_get_clean();
    }
}
