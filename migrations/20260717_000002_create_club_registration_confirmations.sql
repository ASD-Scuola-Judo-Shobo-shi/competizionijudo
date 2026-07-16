CREATE TABLE club_registration_confirmations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    registration_payload JSON NOT NULL,
    expires_at DATETIME NOT NULL,
    confirmed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_club_registration_confirmations_email (email),
    UNIQUE KEY uniq_club_registration_confirmations_token (token_hash),
    KEY idx_club_registration_confirmations_token_expiry (token_hash, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
