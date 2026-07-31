-- Registration options are event-owned fee schedules. Entry snapshots retain
-- the option name and fee that applied when an athlete was enrolled, so later
-- option edits cannot change the value of an existing enrollment.
CREATE TABLE IF NOT EXISTS event_registration_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    fee_cents INT UNSIGNED NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    default_event_id INT
        GENERATED ALWAYS AS (
            CASE
                WHEN is_active = 1 AND is_default = 1 THEN event_id
                ELSE NULL
            END
        ) VIRTUAL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_event_registration_option_identity (event_id, id),
    UNIQUE KEY uniq_event_registration_option_default (default_event_id),
    KEY idx_event_registration_option_name (event_id, name),
    KEY idx_event_registration_options_active (event_id, is_active, sort_order, id),
    CONSTRAINT chk_event_registration_options_default
        CHECK (is_default IN (0, 1)),
    CONSTRAINT chk_event_registration_options_active
        CHECK (is_active IN (0, 1)),
    CONSTRAINT fk_event_registration_options_event
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Give pre-existing events a safe, explicit default. Administrators can replace
-- this zero-fee option when they next edit the event.
INSERT INTO event_registration_options (
    event_id,
    name,
    fee_cents,
    is_default,
    is_active,
    sort_order
)
SELECT event_record.id, 'Standard', 0, 1, 1, 0
FROM events AS event_record
WHERE NOT EXISTS (
    SELECT 1
    FROM event_registration_options AS existing_option
    WHERE existing_option.event_id = event_record.id
      AND existing_option.is_active = 1
);

-- Repair a partially configured event before backfilling its entries.
UPDATE event_registration_options AS registration_option
JOIN (
    SELECT candidate.event_id, MIN(candidate.id) AS default_id
    FROM event_registration_options AS candidate
    WHERE candidate.is_active = 1
      AND NOT EXISTS (
          SELECT 1
          FROM event_registration_options AS existing_default
          WHERE existing_default.event_id = candidate.event_id
            AND existing_default.is_active = 1
            AND existing_default.is_default = 1
      )
    GROUP BY candidate.event_id
) AS missing_default
  ON missing_default.default_id = registration_option.id
SET registration_option.is_default = 1;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'entries'
          AND COLUMN_NAME = 'registration_option_id'
    ),
    'DO 0',
    'ALTER TABLE entries ADD COLUMN registration_option_id INT NULL AFTER athlete_id'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'entries'
          AND COLUMN_NAME = 'registration_option_name'
    ),
    'DO 0',
    'ALTER TABLE entries ADD COLUMN registration_option_name VARCHAR(120) NULL AFTER registration_option_id'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'entries'
          AND COLUMN_NAME = 'registration_fee_cents'
    ),
    'DO 0',
    'ALTER TABLE entries ADD COLUMN registration_fee_cents INT UNSIGNED NULL AFTER registration_option_name'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

UPDATE entries AS entry_record
JOIN event_registration_options AS registration_option
  ON registration_option.event_id = entry_record.event_id
 AND registration_option.is_active = 1
 AND registration_option.is_default = 1
SET entry_record.registration_option_id = registration_option.id,
    entry_record.registration_option_name = registration_option.name,
    entry_record.registration_fee_cents = registration_option.fee_cents
WHERE entry_record.registration_option_id IS NULL
   OR entry_record.registration_option_name IS NULL
   OR entry_record.registration_fee_cents IS NULL;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'entries'
          AND COLUMN_NAME = 'registration_option_id'
          AND IS_NULLABLE = 'YES'
    ),
    'ALTER TABLE entries MODIFY COLUMN registration_option_id INT NOT NULL',
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
          AND TABLE_NAME = 'entries'
          AND COLUMN_NAME = 'registration_option_name'
          AND IS_NULLABLE = 'YES'
    ),
    'ALTER TABLE entries MODIFY COLUMN registration_option_name VARCHAR(120) NOT NULL',
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
          AND TABLE_NAME = 'entries'
          AND COLUMN_NAME = 'registration_fee_cents'
          AND IS_NULLABLE = 'YES'
    ),
    'ALTER TABLE entries MODIFY COLUMN registration_fee_cents INT UNSIGNED NOT NULL',
    'DO 0'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'entries'
          AND INDEX_NAME = 'idx_entries_event_registration_option'
    ),
    'DO 0',
    'ALTER TABLE entries ADD KEY idx_entries_event_registration_option (event_id, registration_option_id)'
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
          AND CONSTRAINT_NAME = 'fk_entries_registration_option'
    ),
    'DO 0',
    'ALTER TABLE entries ADD CONSTRAINT fk_entries_registration_option FOREIGN KEY (event_id, registration_option_id) REFERENCES event_registration_options(event_id, id) ON DELETE RESTRICT'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
