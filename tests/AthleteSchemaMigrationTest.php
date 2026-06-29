<?php

declare(strict_types=1);

namespace Tests;

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
}
