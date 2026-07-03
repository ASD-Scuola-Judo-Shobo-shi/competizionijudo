<?php

declare(strict_types=1);

/**
 * Root front controller — replaces the root.htaccess RewriteRule logic.
 *
 * Responsibilities:
 *  - HTTPS enforcement (belt-and-suspenders with root.htaccess)
 *  - Subdomain → www redirects (prod.*, dev.*, legacy.*)
 *  - Environment prefix routing for the main site
 *  - Pass-through to the correct application entry point
 *
 * The corresponding root.htaccess sends all non-file, non-directory requests
 * here via the RewriteRule ^(.*)$ index.php [QSA,L].
 */

// =====================================================================
// HTTPS ENFORCEMENT
// If root.htaccess isn't active (dev server, misconfiguration), enforce
// HTTPS at the PHP level too.
// =====================================================================
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit();
}

$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';

// =====================================================================
// SUBDOMAIN → www REDIRECTS
// prod.competizionijudo.it  -> www.competizionijudo.it/prod/...
// dev.competizionijudo.it   -> www.competizionijudo.it/dev/...
// legacy.competizionijudo.it -> www.competizionijudo.it/legacy/...
// =====================================================================

if ($host === 'prod.competizionijudo.it') {
    header('Location: https://www.competizionijudo.it/prod' . $request_uri, true, 301);
    exit();
}

if ($host === 'dev.competizionijudo.it') {
    header('Location: https://www.competizionijudo.it/dev' . $request_uri, true, 301);
    exit();
}

if ($host === 'legacy.competizionijudo.it') {
    header('Location: https://www.competizionijudo.it/legacy' . $request_uri, true, 301);
    exit();
}

// =====================================================================
// MAIN SITE — internal environment routing
// =====================================================================

// CHANGE 'prod' to 'legacy' here to swap which site the main domain serves.
$active_environment = 'prod';

$uri_path = parse_url($request_uri, PHP_URL_PATH) ?: '/';

// If the request already carries an environment prefix, use that;
// otherwise prepend the active environment (internal rewrite, no external redirect).
if (preg_match('#^/(prod|dev|legacy)(/|$)#', $uri_path, $matches)) {
    $env_prefix = $matches[1];
} else {
    $env_prefix = $active_environment;

    // Rewrite REQUEST_URI so the downstream application sees the
    // environment prefix — mirrors what root.htaccess RewriteRule did.
    $query_string = parse_url($request_uri, PHP_URL_QUERY) ?? '';
    $_SERVER['REQUEST_URI'] = '/' . $active_environment . $uri_path . ($query_string !== '' ? '?' . $query_string : '');
}

$app_dir = __DIR__ . '/' . $env_prefix;
$entry_point = $app_dir . '/public/index.php';

if (is_file($entry_point)) {
    // Transfer control to the application. The application handles its own
    // response (headers, body, status code).
    require $entry_point;

    return;
}

// Fallback — if the environment directory doesn't exist, return a clean 404.
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not Found';