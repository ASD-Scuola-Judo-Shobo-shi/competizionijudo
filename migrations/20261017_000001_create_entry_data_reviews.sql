-- Record athlete facts at the database write boundary, including imports and duplicate reconciliation.
-- Baselines establish knowledge only from deployment time; no historical timestamps are invented.
CREATE TABLE IF NOT EXISTS athlete_revisions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    athlete_id INT NOT NULL,
    club_id INT NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    first_name VARCHAR(120) NOT NULL,
    gender VARCHAR(1) NOT NULL,
    birth_date DATE NOT NULL,
    weight_kg DECIMAL(6,2) NULL,
    belt VARCHAR(40) NULL,
    membership_number VARCHAR(80) NULL,
    recorded_at DATETIME(6) NOT NULL,
    source VARCHAR(24) NOT NULL DEFAULT 'profile_write',
    KEY idx_revision_athlete_time (athlete_id, recorded_at, id),
    CONSTRAINT fk_revision_athlete_club FOREIGN KEY (athlete_id, club_id)
        REFERENCES athletes(id, club_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO athlete_revisions
    (athlete_id, club_id, last_name, first_name, gender, birth_date, weight_kg, belt, membership_number, recorded_at, source)
SELECT a.id, a.club_id, a.last_name, a.first_name, a.gender, a.birth_date, a.weight_kg, a.belt, a.membership_number, UTC_TIMESTAMP(6), 'baseline'
FROM athletes a WHERE NOT EXISTS (SELECT 1 FROM athlete_revisions r WHERE r.athlete_id = a.id);

DROP TRIGGER IF EXISTS athlete_revision_insert;
CREATE TRIGGER athlete_revision_insert AFTER INSERT ON athletes FOR EACH ROW INSERT INTO athlete_revisions
    (athlete_id, club_id, last_name, first_name, gender, birth_date, weight_kg, belt, membership_number, recorded_at)
VALUES (NEW.id, NEW.club_id, NEW.last_name, NEW.first_name, NEW.gender, NEW.birth_date, NEW.weight_kg, NEW.belt, NEW.membership_number, UTC_TIMESTAMP(6));

DROP TRIGGER IF EXISTS athlete_revision_update;
CREATE TRIGGER athlete_revision_update AFTER UPDATE ON athletes FOR EACH ROW INSERT INTO athlete_revisions
    (athlete_id, club_id, last_name, first_name, gender, birth_date, weight_kg, belt, membership_number, recorded_at)
SELECT NEW.id, NEW.club_id, NEW.last_name, NEW.first_name, NEW.gender, NEW.birth_date, NEW.weight_kg, NEW.belt, NEW.membership_number, UTC_TIMESTAMP(6)
WHERE NOT (CAST(OLD.last_name AS BINARY) <=> CAST(NEW.last_name AS BINARY)
    AND CAST(OLD.first_name AS BINARY) <=> CAST(NEW.first_name AS BINARY)
    AND CAST(OLD.gender AS BINARY) <=> CAST(NEW.gender AS BINARY)
    AND OLD.birth_date <=> NEW.birth_date AND OLD.weight_kg <=> NEW.weight_kg
    AND CAST(OLD.belt AS BINARY) <=> CAST(NEW.belt AS BINARY)
    AND CAST(OLD.membership_number AS BINARY) <=> CAST(NEW.membership_number AS BINARY));

CREATE TABLE IF NOT EXISTS entry_data_reviews (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    entry_id INT NOT NULL,
    revision_id BIGINT NULL,
    requester_club_id INT NULL,
    reason VARCHAR(1000) NOT NULL,
    before_data TEXT NOT NULL,
    proposed_data TEXT NOT NULL,
    base_fingerprint CHAR(64) NOT NULL,
    source_recorded_at DATETIME(6) NOT NULL,
    requested_at DATETIME(6) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    decided_at DATETIME(6) NULL,
    decision_reason VARCHAR(1000) NULL,
    decision_actor VARCHAR(24) NULL,
    KEY idx_review_entry_status (entry_id, status, id),
    KEY idx_review_revision (revision_id),
    CONSTRAINT fk_review_entry FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE,
    CONSTRAINT fk_review_revision FOREIGN KEY (revision_id) REFERENCES athlete_revisions(id) ON DELETE SET NULL,
    CONSTRAINT fk_review_requester FOREIGN KEY (requester_club_id) REFERENCES clubs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
