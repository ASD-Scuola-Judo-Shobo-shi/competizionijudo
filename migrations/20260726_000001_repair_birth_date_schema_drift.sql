-- Repair databases whose legacy birth-date columns survived the recorded rename migration.
SET @athlete_birth_date_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'athletes'
      AND COLUMN_NAME = 'birth_date'
);

SET @athlete_legacy_birth_date_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'athletes'
      AND COLUMN_NAME = 'date_of_birth'
);

SET @migration_sql = CASE
    WHEN @athlete_birth_date_exists > 0 THEN 'DO 0'
    WHEN @athlete_legacy_birth_date_exists > 0
        THEN 'ALTER TABLE athletes CHANGE COLUMN date_of_birth birth_date DATE NOT NULL'
    ELSE 'SELECT missing_required_athletes_birth_date_column'
END;
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @snapshot_birth_date_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'entries'
      AND COLUMN_NAME = 'snapshot_birth_date'
);

SET @legacy_snapshot_birth_date_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'entries'
      AND COLUMN_NAME = 'snapshot_date_of_birth'
);

SET @migration_sql = CASE
    WHEN @snapshot_birth_date_exists > 0 THEN 'DO 0'
    WHEN @legacy_snapshot_birth_date_exists > 0
        THEN 'ALTER TABLE entries CHANGE COLUMN snapshot_date_of_birth snapshot_birth_date DATE NULL'
    ELSE 'ALTER TABLE entries ADD COLUMN snapshot_birth_date DATE NULL'
END;
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'athletes'
          AND COLUMN_NAME = 'birth_date'
    )
    AND EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'entries'
          AND COLUMN_NAME = 'snapshot_birth_date'
    ),
    'DO 0',
    'SELECT incomplete_birth_date_schema_repair'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
