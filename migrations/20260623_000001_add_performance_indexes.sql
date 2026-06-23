-- Performance indexes for common queries

ALTER TABLE athletes ADD INDEX idx_athletes_club_id (club_id);
ALTER TABLE entries ADD INDEX idx_entries_event_club (event_id, club_id);
ALTER TABLE events ADD INDEX idx_events_date (date);
ALTER TABLE events ADD INDEX idx_events_published_closed_date (published, closed, date);