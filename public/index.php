<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Request;

require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI === 'cli-server') {
    $requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $publicFile = __DIR__ . $requestedPath;

    if ($requestedPath !== '/' && is_file($publicFile) && pathinfo($publicFile, PATHINFO_EXTENSION) !== 'php') {
        if (strtolower(pathinfo($publicFile, PATHINFO_EXTENSION)) === 'svgz') {
            header('Content-Type: image/svg+xml');
            header('Content-Encoding: gzip');
            header('Content-Length: ' . (string) filesize($publicFile));
            readfile($publicFile);

            return true;
        }

        return false;
    }
}

$app = new Application(dirname(__DIR__));

(require dirname(__DIR__) . '/routes/web.php')($app->router());

$response = $app->handle(Request::fromGlobals());
$response->send();
