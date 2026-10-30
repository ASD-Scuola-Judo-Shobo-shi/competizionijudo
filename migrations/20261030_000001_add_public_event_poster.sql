-- Existing posters remain private. Public posters are uploaded separately.
SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'public_poster_file'),
    'DO 0',
    'ALTER TABLE events ADD COLUMN public_poster_file VARCHAR(255) NULL AFTER poster_file'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
