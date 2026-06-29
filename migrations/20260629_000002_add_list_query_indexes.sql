-- Keep page ordering and club-scoped competition lookups index-backed.

ALTER TABLE clubs
    ADD INDEX idx_clubs_name_id (name, id);

ALTER TABLE athletes
    ADD INDEX idx_athletes_club_name_id (club_id, last_name, first_name, id),
    DROP INDEX idx_athletes_club_id;

ALTER TABLE entries
    ADD INDEX idx_entries_club_event (club_id, event_id);
