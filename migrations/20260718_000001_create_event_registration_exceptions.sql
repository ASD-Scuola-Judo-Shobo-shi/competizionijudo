-- Migration: Create event_registration_exceptions table and add max_participants to events

-- Add max_participants column to events table
ALTER TABLE events
    ADD COLUMN max_participants INT NULL AFTER registration_deadline;

-- Create event_registration_exceptions table
CREATE TABLE IF NOT EXISTS event_registration_exceptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    club_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_exception (event_id, club_id),
    KEY idx_exception_event (event_id),
    KEY idx_exception_club (club_id),
    CONSTRAINT fk_exception_event
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_exception_club
        FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;