<?php

declare(strict_types=1);

use App\Core\HttpException;
use App\Localization;

function base_path(string $path = ''): string
{
    $base = dirname(__DIR__);

    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function app_base_path(): string
{
    static $cachedUrl = null;
    static $cachedPath = '';

    $appUrl = (string) env('APP_URL', (string) config('app.url', 'http://localhost:8080'));
    if ($cachedUrl === $appUrl) {
        return $cachedPath;
    }

    $path = (string) parse_url($appUrl, PHP_URL_PATH);
    $path = trim($path, '/');

    $cachedUrl = $appUrl;
    $cachedPath = $path === '' ? '' : '/' . $path;

    return $cachedPath;
}

function base_url(string $path = ''): string
{
    if ($path === '') {
        return app_base_path() === '' ? '/' : app_base_path();
    }

    if (
        preg_match('#^[a-z][a-z0-9+\-.]*:#i', $path) === 1
        || str_starts_with($path, '//')
        || str_starts_with($path, '#')
    ) {
        return $path;
    }

    $normalizedPath = '/' . ltrim($path, '/');
    $basePath = app_base_path();

    return $basePath === '' ? $normalizedPath : $basePath . $normalizedPath;
}

function asset_url(string $path): string
{
    $path = ltrim($path, '/');
    $file = base_path('public/' . $path);
    if (!is_file($file)) {
        return base_url('/' . $path);
    }

    return base_url('/' . $path) . '?v=' . substr(hash_file('sha256', $file), 0, 12);
}

function config(string $key, mixed $default = null): mixed
{
    static $items = [];

    if ($items === []) {
        foreach (glob(base_path('config/*.php')) ?: [] as $file) {
            $items[basename($file, '.php')] = require $file;
        }
    }

    $value = $items;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @param array<string, string> $replacements */
function translate(string $key, array $replacements = []): string
{
    return Localization::trans($key, $replacements);
}

/** @param array<string, string> $replacements */
function __(string $key, array $replacements = []): string
{
    return translate($key, $replacements);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function validate_csrf(?string $token): void
{
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) $token)) {
        throw new HttpException(419, __('errors.invalid_csrf'));
    }
}

function load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }
}

/**
 * Build pagination metadata and links.
 *
 * @param int                       $totalItems      Total number of items
 * @param int                       $currentPage     Current page (1-based)
 * @param int                       $perPage         Items per page
 * @param string                    $pageParameter   Query parameter used for the page
 * @param array<string, mixed>|null $queryParameters Query parameters to preserve in links
 * @return array{page: int, per_page: int, total: int, last_page: int, offset: int, links: string}
 */
function paginate(
    int $totalItems,
    int $currentPage,
    int $perPage = 50,
    string $pageParameter = 'page',
    ?array $queryParameters = null,
    ?string $ariaLabel = null
): array {
    $lastPage = max(1, (int) ceil($totalItems / $perPage));
    $page = max(1, min($currentPage, $lastPage));
    $offset = ($page - 1) * $perPage;

    $query = $queryParameters ?? $_GET;
    $ariaLabel ??= __('pagination.label');
    $links = '';
    if ($lastPage > 1) {
        $links .= '<nav class="pagination" role="navigation" aria-label="' . e($ariaLabel) . '">';

        $link = static function (
            int $targetPage,
            string $content,
            string $linkAriaLabel,
            bool $disabled = false,
            bool $active = false
        ) use (
            $query,
            $pageParameter
        ): string {
            $linkQuery = $query;
            $linkQuery[$pageParameter] = $targetPage;
            $url = $disabled ? '#' : '?' . http_build_query($linkQuery);
            $classes = 'pagination-link' . ($disabled ? ' disabled' : '') . ($active ? ' active' : '');
            $ariaCurrent = $active ? ' aria-current="page"' : '';
            $disabledAttributes = $disabled ? ' aria-disabled="true" tabindex="-1"' : '';

            return '<a class="' . $classes . '" href="' . e($url) . '" aria-label="'
                . e($linkAriaLabel) . '"' . $ariaCurrent . $disabledAttributes . '>' . $content . '</a>';
        };

        $atFirstPage = $page === 1;
        $atLastPage = $page === $lastPage;
        $links .= $link(
            1,
            '&laquo;&laquo; ' . e(__('pagination.first')),
            __('pagination.first_page'),
            $atFirstPage
        );
        $links .= $link(
            max(1, $page - 5),
            '&minus;5',
            __('pagination.previous_five_pages'),
            $atFirstPage
        );
        $links .= $link(
            max(1, $page - 1),
            '&lsaquo; ' . e(__('pagination.prev')),
            __('pagination.previous_page'),
            $atFirstPage
        );

        // Page numbers
        $start = max(1, $page - 2);
        $end = min($lastPage, $page + 2);
        for ($i = $start; $i <= $end; $i++) {
            $links .= $link(
                $i,
                (string) $i,
                __('pagination.page_number', ['page' => (string) $i]),
                false,
                $i === $page
            );
        }

        $links .= $link(
            min($lastPage, $page + 1),
            e(__('pagination.next')) . ' &rsaquo;',
            __('pagination.next_page'),
            $atLastPage
        );
        $links .= $link(
            min($lastPage, $page + 5),
            '+5',
            __('pagination.next_five_pages'),
            $atLastPage
        );
        $links .= $link(
            $lastPage,
            e(__('pagination.last')) . ' &raquo;&raquo;',
            __('pagination.last_page'),
            $atLastPage
        );

        $links .= '</nav>';
    }

    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $totalItems,
        'last_page' => $lastPage,
        'offset' => $offset,
        'links' => $links,
    ];
}
