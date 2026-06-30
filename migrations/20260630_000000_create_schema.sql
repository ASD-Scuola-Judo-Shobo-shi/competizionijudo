-- Consolidated fresh-install schema for Competizioni Judo.
-- Existing databases must either contain the complete pre-squash migration
-- history (which MigrationRunner adopts) or be recreated before this runs.

DROP TEMPORARY TABLE IF EXISTS baseline_schema_preflight;
CREATE TEMPORARY TABLE baseline_schema_preflight (
    schema_must_be_empty TINYINT NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT INTO baseline_schema_preflight (schema_must_be_empty) VALUES (1);

-- A discovered application table attempts to duplicate the guard key and
-- aborts before any persistent schema change.
INSERT INTO baseline_schema_preflight (schema_must_be_empty)
SELECT 1
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'clubs',
      'events',
      'athletes',
      'entries',
      'password_reset_tokens',
      'authentication_throttles'
  )
LIMIT 1;

DROP TEMPORARY TABLE baseline_schema_preflight;

CREATE TABLE clubs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    federal_code VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    normalized_email VARCHAR(255)
        GENERATED ALWAYS AS (LOWER(TRIM(email))) STORED,
    phone VARCHAR(80) NOT NULL,
    contact_first_name VARCHAR(120) NOT NULL,
    contact_last_name VARCHAR(120) NOT NULL,
    contact_phone VARCHAR(80) NOT NULL,
    contact_email VARCHAR(255) NULL,
    organization VARCHAR(50) NOT NULL,
    recovery_email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_clubs_federal_code (federal_code),
    UNIQUE KEY uniq_clubs_normalized_email (normalized_email),
    KEY idx_clubs_name_id (name, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    location VARCHAR(255) NOT NULL,
    organizer VARCHAR(255) NULL,
    registration_deadline DATE NULL,
    type ENUM(
        'only_precompetitive',
        'only_competitive',
        'precompetitive_and_competitive'
    ) NULL,
    description TEXT NULL,
    notes TEXT NULL,
    poster_file VARCHAR(255) NULL,
    info_file VARCHAR(255) NULL,
    published TINYINT(1) DEFAULT 0,
    closed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_events_date (date),
    KEY idx_events_published_closed_date (published, closed, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE athletes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    first_name VARCHAR(120) NOT NULL,
    gender ENUM('M', 'F') NOT NULL,
    date_of_birth DATE NOT NULL,
    weight_kg DECIMAL(6,2) NOT NULL,
    belt ENUM(
        'white',
        'white_yellow',
        'yellow',
        'yellow_orange',
        'orange',
        'orange_green',
        'green',
        'green_blue',
        'blue',
        'brown',
        'black',
        'red_white',
        'red'
    ) NULL,
    membership_number VARCHAR(80) NULL,
    notes TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_athletes_club_name_id (club_id, last_name, first_name, id),
    CONSTRAINT fk_athletes_club
        FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    club_id INT NOT NULL,
    athlete_id INT NOT NULL,
    snapshot_last_name VARCHAR(120) NULL,
    snapshot_first_name VARCHAR(120) NULL,
    snapshot_gender VARCHAR(1) NULL,
    snapshot_date_of_birth DATE NULL,
    snapshot_weight_kg DECIMAL(6,2) NULL,
    snapshot_belt VARCHAR(40) NULL,
    snapshot_membership_number VARCHAR(80) NULL,
    snapshot_program VARCHAR(20) NULL,
    snapshot_weight_category VARCHAR(50) NULL,
    snapshot_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_entry (event_id, club_id, athlete_id),
    KEY idx_entries_event_club (event_id, club_id),
    KEY idx_entries_club_event (club_id, event_id),
    CONSTRAINT fk_entries_event
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_entries_club
        FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    CONSTRAINT fk_entries_athlete
        FOREIGN KEY (athlete_id) REFERENCES athletes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_token_hash (token_hash),
    KEY idx_club_id (club_id),
    CONSTRAINT fk_password_reset_tokens_club
        FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE authentication_throttles (
    throttle_key CHAR(64) NOT NULL,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    blocked_until DATETIME NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (throttle_key),
    KEY idx_authentication_throttles_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
