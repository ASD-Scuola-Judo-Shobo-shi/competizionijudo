-- Add max_participants field to events table.
SET @migration_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'max_participants'
    ),
    'DO 0',
    'ALTER TABLE events ADD COLUMN max_participants INT UNSIGNED NULL AFTER closed'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

-- Add check constraint for positive values (MySQL 8.0.16+).
SET @migration_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'events'
          AND CONSTRAINT_NAME = 'chk_events_max_participants_positive'
    ),
    'DO 0',
    'ALTER TABLE events ADD CONSTRAINT chk_events_max_participants_positive CHECK (max_participants IS NULL OR max_participants > 0)'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
