<?php

declare(strict_types=1);

/**
 * Root front controller — replaces the root.htaccess RewriteRule logic.
 *
 * Responsibilities:
 * - HTTPS enforcement (belt-and-suspenders with root.htaccess)
 * - Subdomain → www redirects (prod.*, dev.*, legacy.*)
 * - Environment prefix routing for the main site
 * - Pass-through to the correct application entry point
 *
 * The corresponding root.htaccess sends all non-file, non-directory requests
 * here via the RewriteRule ^(.*)$ index.php [QSA,L].
 */

$host = strtolower((string) parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$allowedHosts = ['www.competizionijudo.it', 'competizionijudo.it', 'prod.competizionijudo.it', 'dev.competizionijudo.it', 'legacy.competizionijudo.it'];

if (!in_array($host, $allowedHosts, true)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo 'Bad Request';
    exit();
}

// HTTPS enforcement must use the canonical host, never a request-supplied one.
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    header('Location: https://www.competizionijudo.it' . $request_uri, true, 301);
    exit();
}

header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

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

    // Externally redirect the browser to the canonical environment-prefixed URL,
    // so the user always sees /prod/... in the address bar.
    $query_string = parse_url($request_uri, PHP_URL_QUERY) ?? '';
    $redirect_path = '/' . $active_environment . $uri_path . ($query_string !== '' ? '?' . $query_string : '');
    header('Location: ' . $redirect_path, true, 302);
    exit();
}

$app_dir = __DIR__ . '/' . $env_prefix;
$entry_point = $app_dir . '/public/index.php';

if (is_file($entry_point)) {
    // =====================================================================
    // DOWNSTREAM ROUTER COMPATIBILITY FIX
    // Strip internal environment prefixes from REQUEST_URI before execution.
    // =====================================================================
    $_SERVER['APP_ROUTE_PREFIX'] = '/' . $env_prefix;
    $cleaned_uri = $_SERVER['REQUEST_URI'];
    if (preg_match('#^/(prod|dev|legacy)(/|$)#', $cleaned_uri, $env_match)) {
        $cleaned_uri = '/' . ltrim(substr($cleaned_uri, strlen($env_match[0])), '/');
    }
    $_SERVER['REQUEST_URI'] = $cleaned_uri;
    // =====================================================================

    // Transfer control to the application. The application handles its own
    // response (headers, body, status code).
    require $entry_point;

    return;
}

// Fallback — if the environment directory doesn't exist, return a clean 404.
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not Found';
