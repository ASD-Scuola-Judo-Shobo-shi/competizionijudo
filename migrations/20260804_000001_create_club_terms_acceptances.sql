CREATE TABLE IF NOT EXISTS club_terms_acceptances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    accepted_by_club_id INT NOT NULL,
    representative_name VARCHAR(255) NOT NULL,
    accepted_account_email VARCHAR(255) NOT NULL,
    terms_version VARCHAR(64) NOT NULL,
    accepted_locale VARCHAR(5) NOT NULL,
    accepted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_club_terms_acceptances_club_accepted (club_id, accepted_at),
    CONSTRAINT fk_club_terms_acceptances_club
        FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    CONSTRAINT fk_club_terms_acceptances_actor
        FOREIGN KEY (accepted_by_club_id) REFERENCES clubs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
