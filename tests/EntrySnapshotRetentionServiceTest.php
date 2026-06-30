<?php

declare(strict_types=1);

namespace Tests;

use App\Service\EntrySnapshotRetentionService;
use PDO;
use PHPUnit\Framework\TestCase;

final class EntrySnapshotRetentionServiceTest extends TestCase
{
    public function testOnlyExpiredSnapshotsFromClosedEventsArePurged(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, closed INTEGER NOT NULL)');
        $database->exec(
            'CREATE TABLE entries (
                id INTEGER PRIMARY KEY, event_id INTEGER NOT NULL, club_id INTEGER NOT NULL,
                snapshot_at TEXT NULL
            )'
        );
        $database->exec('CREATE INDEX idx_entries_event_club ON entries (event_id, club_id)');
        $database->exec('INSERT INTO events (id, closed) VALUES (1, 1), (2, 0)');
        $database->exec(
            "INSERT INTO entries (id, event_id, club_id, snapshot_at) VALUES
             (10, 1, 100, '2024-01-01 00:00:00'),
             (11, 1, 100, '2026-01-01 00:00:00'),
             (12, 2, 100, '2024-01-01 00:00:00'),
             (13, 1, 100, NULL)"
        );

        $plan = $database->prepare(
            'EXPLAIN QUERY PLAN DELETE FROM entries
             WHERE snapshot_at IS NOT NULL AND snapshot_at < ?
               AND event_id IN (SELECT id FROM events WHERE closed = 1)'
        );
        $plan->execute(['2025-06-30 00:00:00']);
        $details = implode(' ', $plan->fetchAll(PDO::FETCH_COLUMN, 3));
        self::assertStringContainsString('idx_entries_event_club', $details);

        $count = (new EntrySnapshotRetentionService($database))->purgeBefore('2025-06-30 00:00:00');

        self::assertSame(1, $count);
        self::assertSame([11, 12, 13], $database->query('SELECT id FROM entries ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    }
}
