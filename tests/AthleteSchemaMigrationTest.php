<?php

declare(strict_types=1);

namespace Tests;

use App\Model\JudoCategory;
use PHPUnit\Framework\TestCase;

final class AthleteSchemaMigrationTest extends TestCase
{
    public function testForwardMigrationAddsAndConditionallyBackfillsWeightCategory(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__) . '/migrations/20260629_000001_add_athlete_weight_category.sql'
        );

        self::assertIsString($migration);
        self::assertStringContainsString('TABLE_SCHEMA = DATABASE()', $migration);
        self::assertStringContainsString("COLUMN_NAME = 'weight_category'", $migration);
        self::assertStringContainsString(
            'ADD COLUMN weight_category VARCHAR(50) NOT NULL DEFAULT',
            $migration
        );
        self::assertStringContainsString("COLUMN_NAME = 'categoria_peso'", $migration);
        self::assertStringContainsString(
            'UPDATE athletes SET weight_category = categoria_peso',
            $migration
        );
        self::assertStringContainsString('PREPARE add_weight_category_statement', $migration);
        self::assertStringContainsString('PREPARE backfill_weight_category_statement', $migration);
        self::assertStringContainsString("'DO 1'", $migration);
        self::assertStringNotContainsString("'SELECT 1'", $migration);
    }

    public function testHistoricalBaselineMigrationRemainsUnchangedByTheRepair(): void
    {
        $baseline = file_get_contents(
            dirname(__DIR__) . '/migrations/20260619_000000_create_baseline_schema.sql'
        );

        self::assertIsString($baseline);
        self::assertStringNotContainsString('weight_category VARCHAR', $baseline);
    }

    public function testClosedEntrySnapshotMigrationRemovesAthleteDerivedColumns(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__) . '/migrations/20260629_000003_snapshot_closed_event_entries.sql'
        );

        self::assertIsString($migration);
        self::assertStringContainsString('snapshot_weight_category VARCHAR(50)', $migration);
        self::assertStringContainsString('snapshot_at TIMESTAMP NULL', $migration);
        self::assertStringContainsString('WHERE e.closed = 1', $migration);
        self::assertStringContainsString('entry_snapshot_weight_limits', $migration);
        self::assertStringContainsString('DROP COLUMN program', $migration);
        self::assertStringContainsString('DROP COLUMN weight_category', $migration);

        preg_match_all(
            "/\\('([^']+)', '([^']+)', (\\d+)\\)/",
            $migration,
            $matches,
            PREG_SET_ORDER
        );
        $migrationLimits = [];
        foreach ($matches as $match) {
            $migrationLimits[$match[1]][$match[2]][] = (int) $match[3];
        }
        self::assertSame(
            JudoCategory::weightCategoryDefinitions()['limits'],
            $migrationLimits
        );
    }
}
