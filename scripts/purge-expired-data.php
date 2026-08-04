<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\FileLogger;
use App\Model\Database;
use App\Service\AccountWorkflowRetentionService;
use App\Service\EntrySnapshotRetentionService;

try {
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $cutoff = $now
        ->sub(new DateInterval('P1Y'))
        ->format('Y-m-d H:i:s');
    $database = Database::connection();
    $entryCount = (new EntrySnapshotRetentionService($database))->purgeBefore($cutoff);
    $accountCounts = (new AccountWorkflowRetentionService($database))->purgeExpired(
        $now->format('Y-m-d H:i:s')
    );
    echo sprintf("Purged %d expired closed-event entries.\n", $entryCount);
    echo sprintf(
        "Purged %d expired registration confirmations and %d password-reset tokens.\n",
        $accountCounts['registration_confirmations'],
        $accountCounts['password_reset_tokens']
    );
} catch (Throwable $exception) {
    $correlationId = hash('sha256', __FILE__ . microtime(true));
    FileLogger::application()->error(
        'privacy.expired_data_purge_failure',
        $exception,
        $correlationId
    );
    fwrite(STDERR, sprintf(
        "Expired-data purge failed. Review the application log (reference %s).\n",
        $correlationId
    ));
    exit(1);
}
