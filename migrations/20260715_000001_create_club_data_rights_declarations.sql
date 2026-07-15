CREATE TABLE IF NOT EXISTS club_data_rights_declarations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    declared_by_club_id INT NOT NULL,
    declaration_version VARCHAR(64) NOT NULL,
    declared_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_club_data_rights_declarations_club_declared (club_id, declared_at),
    CONSTRAINT fk_club_data_rights_declarations_club
        FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    CONSTRAINT fk_club_data_rights_declarations_actor
        FOREIGN KEY (declared_by_club_id) REFERENCES clubs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
