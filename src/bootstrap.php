<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        $baseDir = __DIR__ . '/';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($file)) {
            require $file;
        }
    });
}

require __DIR__ . '/helpers.php';
load_env(dirname(__DIR__) . '/.env');

if (function_exists('env')) {
    $locale = $_SESSION['locale'] ?? env('APP_LOCALE', 'it');
    App\Localization::setLocale($locale);

    if (in_array(strtolower((string) env('APP_AUTO_RUN_MIGRATIONS', 'false')), ['1', 'true', 'yes'], true)) {
        $pdo = App\Model\Database::connection();
        (new App\Model\MigrationRunner($pdo))->run();
    }
}
