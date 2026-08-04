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
        $database->exec(
            'CREATE TABLE events (id INTEGER PRIMARY KEY, closed INTEGER NOT NULL, date TEXT NOT NULL)'
        );
        $database->exec(
            'CREATE TABLE entries (
                id INTEGER PRIMARY KEY, event_id INTEGER NOT NULL, club_id INTEGER NOT NULL,
                snapshot_at TEXT NULL
            )'
        );
        $database->exec('CREATE INDEX idx_entries_event_club ON entries (event_id, club_id)');
        $database->exec(
            "INSERT INTO events (id, closed, date) VALUES
             (1, 1, '2024-01-01'), (2, 0, '2024-01-01'),
             (3, 1, '2026-01-01'), (4, 1, '2025-06-30')"
        );
        $database->exec(
            "INSERT INTO entries (id, event_id, club_id, snapshot_at) VALUES
             (10, 1, 100, '2024-01-01 00:00:00'),
             (11, 1, 100, '2026-01-01 00:00:00'),
             (12, 2, 100, '2024-01-01 00:00:00'),
             (13, 1, 100, NULL),
             (14, 3, 100, NULL),
             (15, 4, 100, '2025-06-30 00:00:00'),
             (16, 4, 100, NULL)"
        );

        $plan = $database->prepare(
            'EXPLAIN QUERY PLAN DELETE FROM entries
             WHERE event_id IN (SELECT id FROM events WHERE closed = 1)
               AND ((snapshot_at IS NOT NULL AND snapshot_at <= ?)
                    OR (snapshot_at IS NULL AND event_id IN (
                        SELECT id FROM events WHERE closed = 1 AND date <= ?
                    )))'
        );
        $plan->execute(['2025-06-30 00:00:00', '2025-06-30']);
        $details = implode(' ', $plan->fetchAll(PDO::FETCH_COLUMN, 3));
        self::assertStringContainsString('idx_entries_event_club', $details);

        $count = (new EntrySnapshotRetentionService($database))->purgeBefore('2025-06-30 00:00:00');

        self::assertSame(4, $count);
        self::assertSame([11, 12, 14], $database->query('SELECT id FROM entries ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    }
}
