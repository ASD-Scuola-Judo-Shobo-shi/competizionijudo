<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Model\Database;
use App\Service\EntrySnapshotRetentionService;

try {
    $cutoff = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->sub(new DateInterval('P1Y'))
        ->format('Y-m-d H:i:s');
    $count = (new EntrySnapshotRetentionService(Database::connection()))->purgeBefore($cutoff);
    echo sprintf("Purged %d expired closed-event entries.\n", $count);
} catch (Throwable) {
    fwrite(STDERR, "Expired-data purge failed. Review the application log.\n");
    exit(1);
}
