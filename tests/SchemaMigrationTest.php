<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class SchemaMigrationTest extends TestCase
{
    private const BASELINE = '/migrations/20260630_000000_create_schema.sql';

    public function testRepositoryContainsOneConsolidatedMigration(): void
    {
        $migrations = glob(dirname(__DIR__) . '/migrations/*.sql') ?: [];

        self::assertSame(
            [dirname(__DIR__) . self::BASELINE],
            array_values($migrations)
        );
    }

    public function testBaselineFailsClosedWhenApplicationTablesAlreadyExist(): void
    {
        $migration = $this->migration();

        self::assertStringContainsString('baseline_schema_preflight', $migration);
        self::assertStringContainsString('schema_must_be_empty TINYINT NOT NULL PRIMARY KEY', $migration);
        self::assertStringContainsString('SELECT 1', $migration);
        self::assertStringContainsString('FROM information_schema.TABLES', $migration);
        self::assertStringNotContainsString('CREATE TABLE IF NOT EXISTS clubs', $migration);
    }

    public function testBaselineDefinesTheCurrentSchemaDirectly(): void
    {
        $migration = $this->migration();

        foreach (
            [
                'CREATE TABLE clubs',
                'CREATE TABLE events',
                'CREATE TABLE athletes',
                'CREATE TABLE entries',
                'CREATE TABLE password_reset_tokens',
                'CREATE TABLE authentication_throttles',
            ] as $table
        ) {
            self::assertStringContainsString($table, $migration);
        }
        self::assertStringContainsString(
            'GENERATED ALWAYS AS (LOWER(TRIM(email))) STORED',
            $migration
        );
        self::assertStringContainsString('snapshot_weight_category VARCHAR(50)', $migration);
        self::assertStringContainsString('snapshot_at TIMESTAMP NULL', $migration);
        self::assertDoesNotMatchRegularExpression('/^\s+(?:age_class|program|weight_category)\s/m', $migration);
    }

    public function testBaselineDefinesRequiredKeysAndIndexes(): void
    {
        $migration = $this->migration();

        foreach (
            [
                'UNIQUE KEY uniq_clubs_normalized_email (normalized_email)',
                'UNIQUE KEY unique_entry (event_id, club_id, athlete_id)',
                'KEY idx_clubs_name_id (name, id)',
                'KEY idx_athletes_club_name_id (club_id, last_name, first_name, id)',
                'KEY idx_entries_event_club (event_id, club_id)',
                'KEY idx_entries_club_event (club_id, event_id)',
                'KEY idx_authentication_throttles_updated_at (updated_at)',
            ] as $definition
        ) {
            self::assertStringContainsString($definition, $migration);
        }
    }

    private function migration(): string
    {
        $migration = file_get_contents(dirname(__DIR__) . self::BASELINE);
        self::assertIsString($migration);

        return $migration;
    }
}
