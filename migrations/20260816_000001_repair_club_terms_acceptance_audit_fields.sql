-- Repair databases that recorded an early club-terms acceptance table without
-- the representative snapshot fields required by the current audit record.
-- Existing evidence is preserved and enriched from the owning club because the
-- historical representative values cannot be reconstructed more precisely.
SET @migration_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'club_terms_acceptances'
          AND COLUMN_NAME = 'representative_name'
    ),
    'DO 0',
    'ALTER TABLE club_terms_acceptances ADD COLUMN representative_name VARCHAR(255) NULL AFTER accepted_by_club_id'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'club_terms_acceptances'
          AND COLUMN_NAME = 'accepted_account_email'
    ),
    'DO 0',
    'ALTER TABLE club_terms_acceptances ADD COLUMN accepted_account_email VARCHAR(255) NULL AFTER representative_name'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

UPDATE club_terms_acceptances AS acceptance
JOIN clubs AS club ON club.id = acceptance.club_id
SET acceptance.representative_name = COALESCE(
        NULLIF(acceptance.representative_name, ''),
        NULLIF(TRIM(CONCAT_WS(' ', club.contact_first_name, club.contact_last_name)), ''),
        club.name
    ),
    acceptance.accepted_account_email = COALESCE(
        NULLIF(acceptance.accepted_account_email, ''),
        club.email
    )
WHERE acceptance.representative_name IS NULL
   OR TRIM(acceptance.representative_name) = ''
   OR acceptance.accepted_account_email IS NULL
   OR TRIM(acceptance.accepted_account_email) = '';

SET @migration_sql = IF(
    EXISTS(
        SELECT 1
        FROM club_terms_acceptances
        WHERE representative_name IS NULL
           OR accepted_account_email IS NULL
    ),
    'SELECT incomplete_club_terms_acceptance_audit_backfill',
    'DO 0'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'club_terms_acceptances'
          AND COLUMN_NAME = 'representative_name'
          AND (
              IS_NULLABLE = 'YES'
              OR DATA_TYPE <> 'varchar'
              OR CHARACTER_MAXIMUM_LENGTH <> 255
          )
    ),
    'ALTER TABLE club_terms_acceptances MODIFY COLUMN representative_name VARCHAR(255) NOT NULL AFTER accepted_by_club_id',
    'DO 0'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'club_terms_acceptances'
          AND COLUMN_NAME = 'accepted_account_email'
          AND (
              IS_NULLABLE = 'YES'
              OR DATA_TYPE <> 'varchar'
              OR CHARACTER_MAXIMUM_LENGTH <> 255
          )
    ),
    'ALTER TABLE club_terms_acceptances MODIFY COLUMN accepted_account_email VARCHAR(255) NOT NULL AFTER representative_name',
    'DO 0'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'club_terms_acceptances'
          AND COLUMN_NAME IN ('representative_name', 'accepted_account_email')
          AND IS_NULLABLE = 'NO'
          AND DATA_TYPE = 'varchar'
          AND CHARACTER_MAXIMUM_LENGTH = 255
    ) = 2,
    'DO 0',
    'SELECT incomplete_club_terms_acceptance_audit_schema'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
