-- Closed events preserve athlete facts and event-year categories.
-- Weight limits match the immutable C31 definition revision baed051.

ALTER TABLE entries
    ADD COLUMN snapshot_last_name VARCHAR(120) NULL AFTER athlete_id,
    ADD COLUMN snapshot_first_name VARCHAR(120) NULL AFTER snapshot_last_name,
    ADD COLUMN snapshot_gender VARCHAR(1) NULL AFTER snapshot_first_name,
    ADD COLUMN snapshot_date_of_birth DATE NULL AFTER snapshot_gender,
    ADD COLUMN snapshot_weight_kg DECIMAL(6,2) NULL AFTER snapshot_date_of_birth,
    ADD COLUMN snapshot_belt VARCHAR(40) NULL AFTER snapshot_weight_kg,
    ADD COLUMN snapshot_membership_number VARCHAR(80) NULL AFTER snapshot_belt,
    ADD COLUMN snapshot_program VARCHAR(20) NULL AFTER snapshot_membership_number,
    ADD COLUMN snapshot_weight_category VARCHAR(50) NULL AFTER snapshot_program,
    ADD COLUMN snapshot_at TIMESTAMP NULL AFTER snapshot_weight_category;

CREATE TEMPORARY TABLE entry_snapshot_backfill (
    entry_id INT NOT NULL PRIMARY KEY,
    class_key VARCHAR(32) NOT NULL,
    program VARCHAR(20) NOT NULL,
    gender VARCHAR(1) NOT NULL,
    weight_kg DECIMAL(6,2) NOT NULL
) ENGINE=InnoDB;

INSERT INTO entry_snapshot_backfill (entry_id, class_key, program, gender, weight_kg)
SELECT
    en.id,
    CASE
        WHEN e.date IS NULL OR a.date_of_birth IS NULL THEN ''
        WHEN YEAR(e.date) - YEAR(a.date_of_birth) < 0 THEN ''
        WHEN YEAR(e.date) - YEAR(a.date_of_birth) <= 5 THEN 'children_a'
        WHEN YEAR(e.date) - YEAR(a.date_of_birth) <= 7 THEN 'children_b'
        WHEN YEAR(e.date) - YEAR(a.date_of_birth) <= 9 THEN 'kids'
        WHEN YEAR(e.date) - YEAR(a.date_of_birth) <= 11 THEN 'youth'
        WHEN YEAR(e.date) - YEAR(a.date_of_birth) = 12 THEN 'pre_cadets_a'
        WHEN YEAR(e.date) - YEAR(a.date_of_birth) <= 14 THEN 'pre_cadets_b'
        WHEN YEAR(e.date) - YEAR(a.date_of_birth) <= 17 THEN 'cadets'
        WHEN YEAR(e.date) - YEAR(a.date_of_birth) <= 20 THEN 'juniors'
        ELSE 'seniors'
    END,
    CASE
        WHEN e.date IS NULL OR a.date_of_birth IS NULL THEN ''
        WHEN YEAR(e.date) - YEAR(a.date_of_birth) < 0 THEN ''
        WHEN YEAR(e.date) - YEAR(a.date_of_birth) <= 11 THEN 'bambini'
        ELSE 'adulti'
    END,
    COALESCE(a.gender, ''),
    COALESCE(a.weight_kg, 0)
FROM entries en
JOIN events e ON e.id = en.event_id
JOIN athletes a ON a.id = en.athlete_id
WHERE e.closed = 1;

UPDATE entries en
JOIN events e ON e.id = en.event_id
JOIN athletes a ON a.id = en.athlete_id
JOIN entry_snapshot_backfill b ON b.entry_id = en.id
SET
    en.snapshot_last_name = a.last_name,
    en.snapshot_first_name = a.first_name,
    en.snapshot_gender = a.gender,
    en.snapshot_date_of_birth = a.date_of_birth,
    en.snapshot_weight_kg = a.weight_kg,
    en.snapshot_belt = a.belt,
    en.snapshot_membership_number = a.membership_number,
    en.snapshot_program = b.program,
    en.snapshot_at = CURRENT_TIMESTAMP;

CREATE TEMPORARY TABLE entry_snapshot_weight_limits (
    class_key VARCHAR(32) NOT NULL,
    gender VARCHAR(1) NOT NULL,
    weight_limit INT NOT NULL,
    PRIMARY KEY (class_key, gender, weight_limit)
) ENGINE=InnoDB;

INSERT INTO entry_snapshot_weight_limits (class_key, gender, weight_limit) VALUES
    ('children_a', '*', 16),
    ('children_a', '*', 18),
    ('children_a', '*', 20),
    ('children_a', '*', 22),
    ('children_a', '*', 24),
    ('children_a', '*', 26),
    ('children_a', '*', 28),
    ('children_a', '*', 30),
    ('children_a', '*', 33),
    ('children_a', '*', 36),
    ('children_b', '*', 18),
    ('children_b', '*', 20),
    ('children_b', '*', 22),
    ('children_b', '*', 24),
    ('children_b', '*', 26),
    ('children_b', '*', 28),
    ('children_b', '*', 30),
    ('children_b', '*', 33),
    ('children_b', '*', 36),
    ('children_b', '*', 40),
    ('kids', '*', 20),
    ('kids', '*', 22),
    ('kids', '*', 24),
    ('kids', '*', 26),
    ('kids', '*', 28),
    ('kids', '*', 30),
    ('kids', '*', 33),
    ('kids', '*', 36),
    ('kids', '*', 40),
    ('kids', '*', 45),
    ('kids', '*', 50),
    ('youth', '*', 26),
    ('youth', '*', 28),
    ('youth', '*', 30),
    ('youth', '*', 33),
    ('youth', '*', 36),
    ('youth', '*', 40),
    ('youth', '*', 45),
    ('youth', '*', 50),
    ('youth', '*', 55),
    ('youth', '*', 60),
    ('youth', '*', 66),
    ('pre_cadets_a', 'M', 36),
    ('pre_cadets_a', 'M', 40),
    ('pre_cadets_a', 'M', 45),
    ('pre_cadets_a', 'M', 50),
    ('pre_cadets_a', 'M', 55),
    ('pre_cadets_a', 'M', 60),
    ('pre_cadets_a', 'M', 66),
    ('pre_cadets_a', 'M', 73),
    ('pre_cadets_a', 'F', 36),
    ('pre_cadets_a', 'F', 40),
    ('pre_cadets_a', 'F', 44),
    ('pre_cadets_a', 'F', 48),
    ('pre_cadets_a', 'F', 52),
    ('pre_cadets_a', 'F', 57),
    ('pre_cadets_a', 'F', 63),
    ('pre_cadets_b', 'M', 38),
    ('pre_cadets_b', 'M', 42),
    ('pre_cadets_b', 'M', 46),
    ('pre_cadets_b', 'M', 50),
    ('pre_cadets_b', 'M', 55),
    ('pre_cadets_b', 'M', 60),
    ('pre_cadets_b', 'M', 66),
    ('pre_cadets_b', 'M', 73),
    ('pre_cadets_b', 'M', 81),
    ('pre_cadets_b', 'F', 40),
    ('pre_cadets_b', 'F', 44),
    ('pre_cadets_b', 'F', 48),
    ('pre_cadets_b', 'F', 52),
    ('pre_cadets_b', 'F', 57),
    ('pre_cadets_b', 'F', 63),
    ('pre_cadets_b', 'F', 70),
    ('cadets', 'M', 46),
    ('cadets', 'M', 50),
    ('cadets', 'M', 55),
    ('cadets', 'M', 60),
    ('cadets', 'M', 66),
    ('cadets', 'M', 73),
    ('cadets', 'M', 81),
    ('cadets', 'M', 90),
    ('cadets', 'F', 40),
    ('cadets', 'F', 44),
    ('cadets', 'F', 48),
    ('cadets', 'F', 52),
    ('cadets', 'F', 57),
    ('cadets', 'F', 63),
    ('cadets', 'F', 70),
    ('juniors', 'M', 60),
    ('juniors', 'M', 66),
    ('juniors', 'M', 73),
    ('juniors', 'M', 81),
    ('juniors', 'M', 90),
    ('juniors', 'M', 100),
    ('juniors', 'F', 48),
    ('juniors', 'F', 52),
    ('juniors', 'F', 57),
    ('juniors', 'F', 63),
    ('juniors', 'F', 70),
    ('juniors', 'F', 78),
    ('seniors', 'M', 60),
    ('seniors', 'M', 66),
    ('seniors', 'M', 73),
    ('seniors', 'M', 81),
    ('seniors', 'M', 90),
    ('seniors', 'M', 100),
    ('seniors', 'F', 48),
    ('seniors', 'F', 52),
    ('seniors', 'F', 57),
    ('seniors', 'F', 63),
    ('seniors', 'F', 70),
    ('seniors', 'F', 78);

UPDATE entries en
JOIN (
    SELECT
        b.entry_id,
        b.class_key,
        b.weight_kg,
        MIN(CASE WHEN l.weight_limit >= b.weight_kg THEN l.weight_limit END) AS next_limit,
        MAX(l.weight_limit) AS maximum_limit
    FROM entry_snapshot_backfill b
    LEFT JOIN entry_snapshot_weight_limits l
        ON l.class_key = b.class_key
       AND (l.gender = '*' OR l.gender = b.gender)
    GROUP BY b.entry_id, b.class_key, b.weight_kg
) resolved ON resolved.entry_id = en.id
SET en.snapshot_weight_category = CASE
    WHEN resolved.class_key = '' OR resolved.weight_kg <= 0 THEN ''
    ELSE COALESCE(
        CONCAT('-', resolved.next_limit, ' kg'),
        CONCAT('+', resolved.maximum_limit, ' kg'),
        ''
    )
END;

DROP TEMPORARY TABLE entry_snapshot_weight_limits;
DROP TEMPORARY TABLE entry_snapshot_backfill;

ALTER TABLE athletes
    DROP COLUMN program,
    DROP COLUMN weight_category;
