-- Add max_participants field to events table

ALTER TABLE events
    ADD COLUMN max_participants INT UNSIGNED NULL AFTER closed;

-- Add check constraint for positive values (MySQL 8.0.16+)
ALTER TABLE events
    ADD CONSTRAINT chk_events_max_participants_positive
    CHECK (max_participants IS NULL OR max_participants > 0);