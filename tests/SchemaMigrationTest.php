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
                dirname(__DIR__) . '/migrations/20260723_000001_rename_date_of_birth_to_birth_date.sql',
                dirname(__DIR__) . '/migrations/20260724_000001_normalize_entry_snapshot_types.sql',
                dirname(__DIR__) . '/migrations/20260726_000001_repair_birth_date_schema_drift.sql',
                dirname(__DIR__) . '/migrations/20260729_000001_add_event_registration_options.sql',
                dirname(__DIR__) . '/migrations/20260729_000002_add_event_sepa_payment_details.sql',
                dirname(__DIR__) . '/migrations/20260730_000001_repair_registration_payment_schema_drift.sql',
                dirname(__DIR__) . '/migrations/20260731_000001_allow_missing_athlete_weight.sql',
                dirname(__DIR__) . '/migrations/20260804_000001_create_club_terms_acceptances.sql',
                dirname(__DIR__) . '/migrations/20260804_000002_add_club_approval_state.sql',
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
        self::assertStringContainsString('birth_date DATE NOT NULL', $migration);
        self::assertStringContainsString('weight_kg DECIMAL(6,2) NULL', $migration);
        self::assertStringContainsString('snapshot_birth_date DATE NULL', $migration);
        self::assertStringNotContainsString('date_of_birth', $migration);
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

    public function testForwardMigrationDefinesVersionedClubTermsAcceptanceEvidence(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__) . '/migrations/20260804_000001_create_club_terms_acceptances.sql'
        );
        self::assertIsString($migration);

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS club_terms_acceptances', $migration);
        self::assertStringContainsString('accepted_by_club_id INT NOT NULL', $migration);
        self::assertStringContainsString('representative_name VARCHAR(255) NOT NULL', $migration);
        self::assertStringContainsString('accepted_account_email VARCHAR(255) NOT NULL', $migration);
        self::assertStringContainsString('terms_version VARCHAR(64) NOT NULL', $migration);
        self::assertStringContainsString('accepted_locale VARCHAR(5) NOT NULL', $migration);
        self::assertStringContainsString('accepted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', $migration);
    }

    public function testForwardMigrationAddsClubApprovalState(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__) . '/migrations/20260804_000002_add_club_approval_state.sql'
        );
        self::assertIsString($migration);

        self::assertStringContainsString('ADD COLUMN approved_at TIMESTAMP NULL DEFAULT NULL', $migration);
        self::assertStringContainsString("COLUMN_NAME = 'approved_at'", $migration);
        self::assertStringContainsString('incomplete_club_approval_column', $migration);
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

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS club_registration_confirmations', $migration);
        self::assertStringContainsString('registration_payload JSON NOT NULL', $migration);
        self::assertStringContainsString('uniq_club_registration_confirmations_token', $migration);
    }

    public function testForwardMigrationRenamesBirthDateColumns(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__) . '/migrations/20260723_000001_rename_date_of_birth_to_birth_date.sql'
        );
        self::assertIsString($migration);

        self::assertStringContainsString(
            'CHANGE COLUMN date_of_birth birth_date DATE NOT NULL',
            $migration
        );
        self::assertStringContainsString(
            'CHANGE COLUMN snapshot_date_of_birth snapshot_birth_date DATE NULL',
            $migration
        );
    }

    public function testForwardMigrationNormalizesLegacyEntrySnapshotTypes(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__) . '/migrations/20260724_000001_normalize_entry_snapshot_types.sql'
        );
        self::assertIsString($migration);

        self::assertStringContainsString("WHEN 'bambini' THEN 'pre-competitive'", $migration);
        self::assertStringContainsString("WHEN 'adulti' THEN 'competitive'", $migration);
    }

    public function testRegistrationOptionMigrationPersistsFeeSnapshotsAndOneDefault(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__) . '/migrations/20260729_000001_add_event_registration_options.sql'
        );
        self::assertIsString($migration);

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS event_registration_options', $migration);
        self::assertStringContainsString('fee_cents INT UNSIGNED NOT NULL', $migration);
        self::assertStringContainsString(
            'UNIQUE KEY uniq_event_registration_option_default (default_event_id)',
            $migration
        );
        self::assertStringContainsString('registration_option_id INT', $migration);
        self::assertStringContainsString('registration_option_name VARCHAR(120)', $migration);
        self::assertStringContainsString('registration_fee_cents INT UNSIGNED', $migration);
        self::assertStringContainsString('fk_entries_registration_option', $migration);
        self::assertStringContainsString(
            'REFERENCES event_registration_options(event_id, id)',
            $migration
        );
    }

    public function testSepaMigrationStoresEveryEventPaymentFieldInTheDatabase(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__) . '/migrations/20260729_000002_add_event_sepa_payment_details.sql'
        );
        self::assertIsString($migration);

        self::assertStringContainsString('sepa_account_holder VARCHAR(70)', $migration);
        self::assertStringContainsString('sepa_iban VARCHAR(34)', $migration);
        self::assertStringContainsString('sepa_bic VARCHAR(11)', $migration);
        self::assertStringNotContainsString('EVENT_REGISTRATION_', $migration);
    }

    public function testForwardMigrationRepairsRecordedRegistrationPaymentSchemaDrift(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__)
            . '/migrations/20260730_000001_repair_registration_payment_schema_drift.sql'
        );
        self::assertIsString($migration);

        self::assertStringContainsString(
            'ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1',
            $migration
        );
        self::assertStringContainsString(
            'ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0',
            $migration
        );
        self::assertStringContainsString(
            'ADD COLUMN registration_option_id INT NULL',
            $migration
        );
        self::assertStringContainsString(
            'ADD COLUMN registration_option_name VARCHAR(120) NULL',
            $migration
        );
        self::assertStringContainsString(
            'ADD COLUMN registration_fee_cents INT UNSIGNED NULL',
            $migration
        );
        self::assertStringContainsString(
            'REFERENCES event_registration_options(event_id, id)',
            $migration
        );
    }

    public function testForwardMigrationRepairsRecordedBirthDateSchemaDrift(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__) . '/migrations/20260726_000001_repair_birth_date_schema_drift.sql'
        );
        self::assertIsString($migration);

        self::assertStringContainsString(
            'CHANGE COLUMN date_of_birth birth_date DATE NOT NULL',
            $migration
        );
        self::assertStringContainsString(
            'CHANGE COLUMN snapshot_date_of_birth snapshot_birth_date DATE NULL',
            $migration
        );
        self::assertStringContainsString(
            'ADD COLUMN snapshot_birth_date DATE NULL',
            $migration
        );
        self::assertStringContainsString('incomplete_birth_date_schema_repair', $migration);
    }

    public function testForwardMigrationAllowsImportedAthletesToHaveNoWeight(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__) . '/migrations/20260731_000001_allow_missing_athlete_weight.sql'
        );
        self::assertIsString($migration);

        self::assertStringContainsString('MODIFY COLUMN weight_kg DECIMAL(6,2) NULL', $migration);
    }

    private function migration(): string
    {
        $migration = file_get_contents(dirname(__DIR__) . self::BASELINE);
        self::assertIsString($migration);

        return $migration;
    }
}
