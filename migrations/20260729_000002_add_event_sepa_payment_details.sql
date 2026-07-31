-- Store every event-specific SEPA business value in the database. There is no
-- environment fallback: a paid registration option requires these details.
SET @migration_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'events'
          AND COLUMN_NAME = 'sepa_account_holder'
    ),
    'DO 0',
    'ALTER TABLE events ADD COLUMN sepa_account_holder VARCHAR(70) NULL AFTER max_participants'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'events'
          AND COLUMN_NAME = 'sepa_iban'
    ),
    'DO 0',
    'ALTER TABLE events ADD COLUMN sepa_iban VARCHAR(34) NULL AFTER sepa_account_holder'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'events'
          AND COLUMN_NAME = 'sepa_bic'
    ),
    'DO 0',
    'ALTER TABLE events ADD COLUMN sepa_bic VARCHAR(11) NULL AFTER sepa_iban'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
