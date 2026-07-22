CREATE TABLE IF NOT EXISTS event_registration_exceptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    club_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_event_club (event_id, club_id),
    KEY idx_event_registration_exceptions_event_id (event_id),
    KEY idx_event_registration_exceptions_club_id (club_id),
    CONSTRAINT fk_event_registration_exceptions_event
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_registration_exceptions_club
        FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
