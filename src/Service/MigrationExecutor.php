<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Database;
use App\Model\MigrationException;
use App\Model\MigrationRunner;
use Throwable;

class MigrationExecutor
{
    public function run(): void
    {
        $pdo = Database::connection();
        (new MigrationRunner($pdo))->run();
    }

    public function failureDetail(Throwable $exception): string
    {
        $cause = $exception;
        while ($cause instanceof MigrationException && $cause->getPrevious() instanceof Throwable) {
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
}
