-- Archiving preserves tournaments, registrations and documents. Existing events
-- remain active until explicitly archived by an administrator or their owner.
SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'archived_at'),
    'DO 0',
    'ALTER TABLE events ADD COLUMN archived_at DATETIME NULL'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND INDEX_NAME = 'idx_events_archive_date'),
    'DO 0',
    'ALTER TABLE events ADD INDEX idx_events_archive_date (archived_at, date, id)'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND INDEX_NAME = 'idx_events_owner_archive_date'),
    'DO 0',
    'ALTER TABLE events ADD INDEX idx_events_owner_archive_date (owner_club_id, archived_at, date, id)'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
