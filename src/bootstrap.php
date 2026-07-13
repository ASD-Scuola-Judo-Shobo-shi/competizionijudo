<?php

declare(strict_types=1);

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
$routePrefix = $_SERVER['APP_ROUTE_PREFIX'] ?? null;
load_env(dirname(__DIR__) . '/.env');
if (is_string($routePrefix) && $routePrefix !== '') {
    $_SERVER['APP_ROUTE_PREFIX'] = $routePrefix;
}

App\Core\Session::configureFromEnvironment();
App\Core\Session::start();

if (function_exists('env')) {
    $locale = $_SESSION['locale'] ?? env('APP_LOCALE', 'it');
    App\Localization::setLocale($locale);

    $appEnv = strtolower((string) env('APP_ENV', 'production'));
    if ($appEnv === 'production' && !defined('PHPUNIT_RUNNING')) {
        App\Core\ProductionConfiguration::assertReady(App\Core\FileLogger::application());
    }

    if (($appEnv === 'local' || $appEnv === 'development') && !defined('PHPUNIT_RUNNING')) {
        $pdo = App\Model\Database::connection();
        (new App\Model\MigrationRunner($pdo))->run();
    }
}
