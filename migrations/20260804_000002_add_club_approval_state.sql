-- Club activation lifecycle (decision D-03): clubs start pending and must be
-- approved by an administrator before they can authenticate. The quota limits
-- themselves are environment-configured and enforced by the application.
SET @club_approved_at_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clubs'
      AND COLUMN_NAME = 'approved_at'
);

SET @migration_sql = IF(
    @club_approved_at_exists > 0,
    'DO 0',
    'ALTER TABLE clubs ADD COLUMN approved_at TIMESTAMP NULL DEFAULT NULL'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'clubs'
          AND COLUMN_NAME = 'approved_at'
    ),
    'DO 0',
    'SELECT incomplete_club_approval_column'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
