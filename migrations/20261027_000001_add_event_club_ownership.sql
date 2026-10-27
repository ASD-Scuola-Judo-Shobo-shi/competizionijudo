-- Existing tournaments remain administrator-managed. Deleting a club preserves
-- its tournaments and returns their management to administrators.
SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'owner_club_id'),
    'DO 0',
    'ALTER TABLE events ADD COLUMN owner_club_id INT NULL'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND INDEX_NAME = 'idx_events_owner_date'),
    'DO 0',
    'ALTER TABLE events ADD INDEX idx_events_owner_date (owner_club_id, date, id)'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS
           WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND CONSTRAINT_NAME = 'fk_events_owner_club'),
    'DO 0',
    'ALTER TABLE events ADD CONSTRAINT fk_events_owner_club FOREIGN KEY (owner_club_id) REFERENCES clubs(id) ON DELETE SET NULL'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
