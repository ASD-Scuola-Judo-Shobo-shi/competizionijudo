<?php

declare(strict_types=1);

namespace Tests;

use App\Service\AccountWorkflowRetentionService;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class AccountWorkflowRetentionServiceTest extends TestCase
{
    public function testExpiredAndLegacyConsumedAccountWorkflowRecordsArePurged(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec(
            'CREATE TABLE club_registration_confirmations (
                id INTEGER PRIMARY KEY, expires_at TEXT NOT NULL
            );
            CREATE TABLE password_reset_tokens (
                id INTEGER PRIMARY KEY, expires_at TEXT NOT NULL, used INTEGER NOT NULL DEFAULT 0
            )'
        );
        $database->exec(
            "INSERT INTO club_registration_confirmations (id, expires_at) VALUES
             (1, '2026-08-03 12:00:00'), (2, '2026-08-05 12:00:00');
             INSERT INTO password_reset_tokens (id, expires_at, used) VALUES
             (10, '2026-08-03 12:00:00', 0),
             (11, '2026-08-05 12:00:00', 1),
             (12, '2026-08-05 12:00:00', 0)"
        );

        $counts = (new AccountWorkflowRetentionService($database))->purgeExpired(
            '2026-08-04 12:00:00'
        );

        self::assertSame([
            'registration_confirmations' => 1,
            'password_reset_tokens' => 2,
        ], $counts);
        self::assertSame(
            [2],
            $database->query(
                'SELECT id FROM club_registration_confirmations ORDER BY id'
            )->fetchAll(PDO::FETCH_COLUMN)
        );
        self::assertSame(
            [12],
            $database->query('SELECT id FROM password_reset_tokens ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)
        );
        self::assertFalse($database->inTransaction());
    }

    public function testADeleteFailureRollsBackTheWholePurge(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec(
            'CREATE TABLE club_registration_confirmations (
                id INTEGER PRIMARY KEY, expires_at TEXT NOT NULL
            );
            CREATE TABLE password_reset_tokens (
                id INTEGER PRIMARY KEY, expires_at TEXT NOT NULL, used INTEGER NOT NULL DEFAULT 0
            );
            INSERT INTO club_registration_confirmations (id, expires_at)
                VALUES (1, \'2026-08-03 12:00:00\');
            INSERT INTO password_reset_tokens (id, expires_at, used)
                VALUES (10, \'2026-08-03 12:00:00\', 0);
            CREATE TRIGGER prevent_reset_token_delete
                BEFORE DELETE ON password_reset_tokens
                BEGIN SELECT RAISE(ABORT, \'synthetic delete failure\'); END'
        );

        try {
            (new AccountWorkflowRetentionService($database))->purgeExpired(
                '2026-08-04 12:00:00'
            );
            self::fail('The synthetic delete failure should escape the purge.');
        } catch (PDOException $exception) {
            self::assertStringContainsString('synthetic delete failure', $exception->getMessage());
        }

        self::assertFalse($database->inTransaction());
        self::assertSame(1, (int) $database->query(
            'SELECT COUNT(*) FROM club_registration_confirmations'
        )->fetchColumn());
        self::assertSame(1, (int) $database->query(
            'SELECT COUNT(*) FROM password_reset_tokens'
        )->fetchColumn());
    }
}
