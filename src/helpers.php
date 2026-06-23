<?php

declare(strict_types=1);

use App\Localization;

function base_path(string $path = ''): string
{
    $base = dirname(__DIR__);

    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
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

/** @return array{age_below: int|null, program: string, weight_category: string} */
function calculateJudoCategory(string $birth, string $gender, float $weight, int $eventYear = 2026): array
{
    return App\Model\JudoCategory::calculate($birth, $gender, $weight, $eventYear);
}

function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

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
        http_response_code(419);
        echo 'Invalid CSRF token';
        exit;
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

/** @return list<array{label: string, url: string, paths: list<string>, query?: array<string, list<string>>}> */
function get_current_path(): string
{
    return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
}

function build_submenu(string $currentPath, bool $isAdmin, bool $isLoggedIn): array
{
    $competitionPaths = ['/events.php', '/event_details.php', '/event_register.php', '/event_details.php'];
    $clubPaths = ['/club_register.php', '/club_login.php', '/club_forgot_password.php', '/club_reset_password.php', '/club_area.php', '/clubs.php'];
    $adminPaths = ['/admin_login.php', '/admin.php', '/admin_manage_clubs.php', '/admin_manage_events.php', '/admin_add_event.php', '/admin_edit_club.php', '/admin_edit_event.php', '/admin_logout.php'];

    $showSubmenu = in_array($currentPath, $competitionPaths, true)
        || in_array($currentPath, $clubPaths, true)
        || in_array($currentPath, $adminPaths, true);

    if (!$showSubmenu) {
        return [];
    }

    $submenuItems = [];
    if (in_array($currentPath, $competitionPaths, true)) {
        $submenuItems = [
            ['label' => translate('events.submenu.showcase'), 'url' => '/events.php', 'paths' => ['/events.php']],
            ['label' => translate('events.submenu.details'), 'url' => '/event_details.php', 'paths' => ['/event_details.php']],
            ['label' => translate('events.submenu.registration'), 'url' => '/event_register.php', 'paths' => ['/event_register.php']],
        ];
    } elseif (in_array($currentPath, $adminPaths, true)) {
        if ($isAdmin) {
            $submenuItems[] = ['label' => translate('admin.submenu.manage_clubs'), 'url' => '/admin_manage_clubs.php', 'paths' => ['/admin_manage_clubs.php', '/admin_edit_club.php']];
            $submenuItems[] = ['label' => translate('admin.submenu.manage_events'), 'url' => '/admin_manage_events.php', 'paths' => ['/admin_manage_events.php']];
            $submenuItems[] = ['label' => translate('admin.submenu.add_event'), 'url' => '/admin_add_event.php', 'paths' => ['/admin_add_event.php']];
            $submenuItems[] = ['label' => translate('admin.submenu.logout'), 'url' => '/admin_logout.php', 'paths' => ['/admin_logout.php']];
        } else {
            $submenuItems[] = ['label' => translate('nav.login'), 'url' => '/admin_login.php', 'paths' => ['/admin_login.php']];
        }
    } elseif (in_array($currentPath, $clubPaths, true)) {
        $submenuItems[] = ['label' => translate('club.list'), 'url' => '/clubs.php', 'paths' => ['/clubs.php']];
        if (!$isLoggedIn) {
            $submenuItems[] = ['label' => translate('nav.login'), 'url' => '/club_login.php', 'paths' => ['/club_login.php']];
        } else {
            $submenuItems[] = ['label' => translate('club.area.submenu.manage'), 'url' => '/club_area.php', 'paths' => ['/club_area.php'], 'query' => ['view' => ['', 'list']]];
            $submenuItems[] = ['label' => translate('club.area.submenu.add'), 'url' => '/club_area.php?view=add', 'paths' => ['/club_area.php'], 'query' => ['view' => ['add']]];
        }
    }

    return $submenuItems;
}

/**
 * Build pagination metadata and links.
 *
 * @param int  $totalItems Total number of items
 * @param int  $currentPage Current page (1-based)
 * @param int  $perPage    Items per page
 * @return array{page: int, per_page: int, total: int, last_page: int, offset: int, links: string}
 */
function paginate(int $totalItems, int $currentPage, int $perPage = 50): array
{
    $lastPage = max(1, (int) ceil($totalItems / $perPage));
    $page = max(1, min($currentPage, $lastPage));
    $offset = ($page - 1) * $perPage;

    $query = $_GET;
    $links = '';
    if ($lastPage > 1) {
        $links .= '<nav class="pagination" role="navigation" aria-label="Pagination">';

        // Prev
        $prevDisabled = $page <= 1 ? ' disabled' : '';
        $prevUrl = '#';
        if ($page > 1) {
            $query['page'] = $page - 1;
            $prevUrl = '?' . http_build_query($query);
        }
        $links .= '<a class="pagination-link' . $prevDisabled . '" href="' . e($prevUrl) . '" aria-label="Previous page">&laquo; ' . translate('pagination.prev') . '</a>';

        // Page numbers
        $start = max(1, $page - 2);
        $end = min($lastPage, $page + 2);
        for ($i = $start; $i <= $end; $i++) {
            $query['page'] = $i;
            $active = $i === $page ? ' active' : '';
            $links .= '<a class="pagination-link' . $active . '" href="?' . e(http_build_query($query)) . '">' . $i . '</a>';
        }

        // Next
        $nextDisabled = $page >= $lastPage ? ' disabled' : '';
        $nextUrl = '#';
        if ($page < $lastPage) {
            $query['page'] = $page + 1;
            $nextUrl = '?' . http_build_query($query);
        }
        $links .= '<a class="pagination-link' . $nextDisabled . '" href="' . e($nextUrl) . '" aria-label="Next page">' . translate('pagination.next') . ' &raquo;</a>';

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