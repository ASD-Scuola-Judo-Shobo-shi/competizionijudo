CREATE TABLE IF NOT EXISTS authentication_throttles (
    throttle_key CHAR(64) NOT NULL,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    blocked_until DATETIME NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (throttle_key),
    INDEX idx_authentication_throttles_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
