<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class SchemaMigrationTest extends TestCase
{
    private const BASELINE = '/migrations/20260630_000000_create_schema.sql';

    public function testRepositoryContainsBaselineAndForwardMigrations(): void
    {
        $migrations = glob(dirname(__DIR__) . '/migrations/*.sql') ?: [];

        self::assertSame(
            [
                dirname(__DIR__) . self::BASELINE,
                dirname(__DIR__) . '/migrations/20260715_000001_create_club_data_rights_declarations.sql',
                dirname(__DIR__) . '/migrations/20260716_000001_consolidate_club_contacts_and_add_addresses.sql',
                dirname(__DIR__) . '/migrations/20260716_000002_rename_club_organization_to_affiliation.sql',
                dirname(__DIR__) . '/migrations/20260717_000001_make_club_affiliation_nullable_and_multiple.sql',
                dirname(__DIR__) . '/migrations/20260717_000002_create_club_registration_confirmations.sql',
                dirname(__DIR__) . '/migrations/20260717_000003_add_max_participants_to_events.sql',
                dirname(__DIR__) . '/migrations/20260718_000001_create_event_registration_exceptions.sql',
            ],
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

    public function testBaselineDefinesTheConsolidatedHistoricalSchema(): void
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

    public function testForwardMigrationDefinesClubRightsDeclarationEvidence(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__) . '/migrations/20260715_000001_create_club_data_rights_declarations.sql'
        );
        self::assertIsString($migration);

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS club_data_rights_declarations', $migration);
        self::assertStringContainsString('declared_by_club_id INT NOT NULL', $migration);
        self::assertStringContainsString('declaration_version VARCHAR(64) NOT NULL', $migration);
        self::assertStringContainsString('declared_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', $migration);
    }

    public function testForwardMigrationConsolidatesClubContactsAndAddsAddresses(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__) . '/migrations/20260716_000001_consolidate_club_contacts_and_add_addresses.sql'
        );
        self::assertIsString($migration);

        self::assertStringContainsString('SET phone = contact_phone', $migration);
        self::assertStringContainsString('DROP COLUMN contact_phone', $migration);
        self::assertStringContainsString('DROP COLUMN contact_email', $migration);
        self::assertStringContainsString('DROP COLUMN recovery_email', $migration);
        self::assertStringContainsString('ADD COLUMN city VARCHAR(120) NOT NULL', $migration);
        self::assertStringContainsString('ADD COLUMN province VARCHAR(120) NOT NULL', $migration);
    }

    public function testForwardMigrationRenamesOrganizationToAffiliation(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__) . '/migrations/20260716_000002_rename_club_organization_to_affiliation.sql'
        );
        self::assertIsString($migration);

        self::assertStringContainsString('CHANGE COLUMN organization affiliation VARCHAR(50) NOT NULL', $migration);
    }

    public function testForwardMigrationMakesAffiliationsNullableAndMultiValueReady(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__) . '/migrations/20260717_000001_make_club_affiliation_nullable_and_multiple.sql'
        );
        self::assertIsString($migration);

        self::assertStringContainsString('MODIFY COLUMN affiliation TEXT NULL', $migration);
    }

    public function testForwardMigrationCreatesRegistrationConfirmations(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__) . '/migrations/20260717_000002_create_club_registration_confirmations.sql'
        );
        self::assertIsString($migration);

        self::assertStringContainsString('CREATE TABLE club_registration_confirmations', $migration);
        self::assertStringContainsString('registration_payload JSON NOT NULL', $migration);
        self::assertStringContainsString('uniq_club_registration_confirmations_token', $migration);
    }

    private function migration(): string
    {
        $migration = file_get_contents(dirname(__DIR__) . self::BASELINE);
        self::assertIsString($migration);

        return $migration;
    }
}
