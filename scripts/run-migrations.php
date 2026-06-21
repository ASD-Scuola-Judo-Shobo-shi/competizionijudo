<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Model\Database;
use App\Model\MigrationRunner;

$pdo = Database::connection();
$runner = new MigrationRunner($pdo);

try {
    $runner->run();
    echo "Migrations applied successfully.\n";
} catch (Throwable $e) {
    echo 'Migration error: ' . $e->getMessage() . "\n";
    exit(1);
}
