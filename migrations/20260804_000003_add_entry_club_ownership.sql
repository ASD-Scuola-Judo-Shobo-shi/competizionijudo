-- Enforce entry ownership: every entry must reference an athlete that
-- belongs to the same club (remediation roadmap ownership rule). The
-- composite foreign key reuses the existing single-column athlete foreign
-- key index as its prefix index.
SET @entries_orphaned = (
    SELECT COUNT(*)
    FROM entries AS entry_record
    LEFT JOIN athletes AS athlete_record
      ON athlete_record.id = entry_record.athlete_id
     AND athlete_record.club_id = entry_record.club_id
    WHERE athlete_record.id IS NULL
);

SET @athletes_id_club_key_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'athletes'
      AND INDEX_NAME = 'uq_athletes_id_club'
);

SET @entry_ownership_fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'entries'
      AND CONSTRAINT_NAME = 'fk_entries_athlete_club'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @migration_sql = IF(
    @athletes_id_club_key_exists > 0,
    'DO 0',
    'ALTER TABLE athletes ADD UNIQUE KEY uq_athletes_id_club (id, club_id)'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    @entry_ownership_fk_exists > 0,
    'DO 0',
    IF(
        @entries_orphaned > 0,
        'SELECT entry_ownership_preflight_failed',
        'ALTER TABLE entries ADD CONSTRAINT fk_entries_athlete_club FOREIGN KEY (athlete_id, club_id) REFERENCES athletes(id, club_id) ON DELETE CASCADE'
    )
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'entries'
          AND CONSTRAINT_NAME = 'fk_entries_athlete_club'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ),
    'DO 0',
    'SELECT incomplete_entry_ownership_constraint'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
