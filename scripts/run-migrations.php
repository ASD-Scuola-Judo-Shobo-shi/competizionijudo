<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/helpers.php';

load_env(dirname(__DIR__) . '/.env');

use App\Model\MigrationException;
use App\Service\MigrationExecutor;

$executor = new MigrationExecutor();

try {
    $executor->run();
    echo "Migrations applied successfully.\n";
} catch (MigrationException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    fwrite(STDERR, $executor->failureDetail($exception) . "\n");
    exit(1);
} catch (Throwable $exception) {
    fwrite(STDERR, "Migration failed before a version could be applied.\n");
    fwrite(STDERR, $executor->failureDetail($exception) . "\n");
    exit(1);
}
