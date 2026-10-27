-- Additional managing clubs share event administration; the creator club remains
-- authorized through owner_club_id and cannot be removed through this mapping.
CREATE TABLE IF NOT EXISTS event_managers (
    event_id INT NOT NULL,
    club_id INT NOT NULL,
    PRIMARY KEY (event_id, club_id),
    KEY idx_event_managers_club_event (club_id, event_id),
    CONSTRAINT fk_event_managers_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_managers_club FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
