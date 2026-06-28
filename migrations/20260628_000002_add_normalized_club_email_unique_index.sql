CREATE TEMPORARY TABLE IF NOT EXISTS club_email_uniqueness_preflight (
    email_hash BINARY(32) NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT INTO club_email_uniqueness_preflight (email_hash)
SELECT UNHEX(SHA2(LOWER(TRIM(email)), 256)) FROM clubs;

DROP TEMPORARY TABLE club_email_uniqueness_preflight;

UPDATE clubs SET email = LOWER(TRIM(email));

ALTER TABLE clubs
    ADD COLUMN normalized_email VARCHAR(255)
    GENERATED ALWAYS AS (LOWER(TRIM(email))) STORED;

CREATE UNIQUE INDEX uniq_clubs_normalized_email ON clubs (normalized_email);
