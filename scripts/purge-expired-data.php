<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\FileLogger;
use App\Model\Database;
use App\Service\AccountWorkflowRetentionService;
use App\Service\EntrySnapshotRetentionService;
use App\Service\PrivacyPurgeEvidence;

$evidence = new PrivacyPurgeEvidence(base_path('var/log/privacy-purge-evidence.log'));

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
    $logPath = base_path('var/log/application.log');
    $logAgeDays = null;
    if (is_file($logPath)) {
        $logAgeDays = max(0, (int) floor(($now->getTimestamp() - (int) filemtime($logPath)) / 86400));
    }
    $evidence->record('ok', [
        'entries_purged' => $entryCount,
        'registration_confirmations_purged' => $accountCounts['registration_confirmations'],
        'password_reset_tokens_purged' => $accountCounts['password_reset_tokens'],
        'log_retention_days' => (int) env('APP_LOG_RETENTION_DAYS', '30'),
        'backup_retention_days' => (int) env('APP_BACKUP_RETENTION_DAYS', '30'),
        'application_log_age_days' => $logAgeDays,
    ]);
    echo sprintf("Purged %d expired closed-event entries.\n", $entryCount);
    echo sprintf(
        "Purged %d expired registration confirmations and %d password-reset tokens.\n",
        $accountCounts['registration_confirmations'],
        $accountCounts['password_reset_tokens']
    );
} catch (Throwable $exception) {
    $correlationId = hash('sha256', __FILE__ . microtime(true));
    try {
        $evidence->record('failed', [], $correlationId);
    } catch (Throwable) {
        // Evidence is unavailable; keep the original failure path.
    }
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
