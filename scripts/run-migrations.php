<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/helpers.php';

load_env(dirname(__DIR__) . '/.env');

use App\Model\Database;
use App\Model\MigrationException;
use App\Model\MigrationRunner;

function migration_failure_detail(\Throwable $exception): string
{
    $cause = $exception;
    while ($cause instanceof MigrationException && $cause->getPrevious() instanceof \Throwable) {
        $cause = $cause->getPrevious();
    }

    $message = preg_replace('/\s+/', ' ', trim($cause->getMessage()));
    if (!is_string($message) || $message === '') {
        $message = 'No diagnostic message was provided.';
    }

    $password = getenv('DB_PASS');
    if (is_string($password) && $password !== '') {
        $message = str_replace($password, '[redacted]', $message);
    }
    $message = preg_replace('/\b((?:db_pass|password|pwd)\s*=)\s*[^\s;]+/i', '$1[redacted]', $message)
        ?? $message;

    return sprintf('Migration diagnostic (%s): %s', get_debug_type($cause), mb_substr($message, 0, 500));
}

try {
    $pdo = Database::connection();
    $runner = new MigrationRunner($pdo);
    $runner->run();
    echo "Migrations applied successfully.\n";
} catch (MigrationException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    fwrite(STDERR, migration_failure_detail($exception) . "\n");
    exit(1);
} catch (Throwable $exception) {
    fwrite(STDERR, "Migration failed before a version could be applied.\n");
    fwrite(STDERR, migration_failure_detail($exception) . "\n");
    exit(1);
}
