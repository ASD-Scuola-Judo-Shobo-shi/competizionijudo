-- Baseline schema for Competizioni Judo
-- This schema includes the current expected tables and columns used by the modern MVC app.

CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clubs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    federal_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(80) NOT NULL,
    contact_first_name VARCHAR(120) NOT NULL,
    contact_last_name VARCHAR(120) NOT NULL,
    contact_phone VARCHAR(80) NOT NULL,
    contact_email VARCHAR(255) NULL,
    organization VARCHAR(50) NOT NULL,
    recovery_email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    location VARCHAR(255) NULL,
    organizer VARCHAR(255) NULL,
    registration_deadline DATE NULL,
    type ENUM('only_precompetitive', 'only_competitive', 'precompetitive_and_competitive') NULL,
    description TEXT NULL,
    notes TEXT NULL,
    poster_file VARCHAR(255) NULL,
    info_file VARCHAR(255) NULL,
    published TINYINT(1) DEFAULT 0,
    closed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS athletes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    first_name VARCHAR(120) NOT NULL,
    gender ENUM('M','F') NOT NULL,
    date_of_birth DATE NOT NULL,
    weight_kg DECIMAL(6,2) NOT NULL,
    belt ENUM('white','white_yellow','yellow','yellow_orange','orange','orange_green','green','green_blue','blue','brown','black','red_white','red') NULL,
    program VARCHAR(20) NOT NULL,
    membership_number VARCHAR(80) NULL,
    notes TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    club_id INT NOT NULL,
    athlete_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_entry (event_id, club_id, athlete_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (athlete_id) REFERENCES athletes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
