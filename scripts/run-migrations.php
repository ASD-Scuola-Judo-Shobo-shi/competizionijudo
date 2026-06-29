<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Model\Database;
use App\Model\MigrationException;
use App\Model\MigrationRunner;

try {
    $pdo = Database::connection();
    $runner = new MigrationRunner($pdo);
    $runner->run();
    echo "Migrations applied successfully.\n";
} catch (MigrationException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, "Migration failed before a version could be applied.\n");
    exit(1);
}
