UPDATE events SET location = 'Sede da definire' WHERE location IS NULL OR location = '';
ALTER TABLE events MODIFY COLUMN location VARCHAR(255) NOT NULL;