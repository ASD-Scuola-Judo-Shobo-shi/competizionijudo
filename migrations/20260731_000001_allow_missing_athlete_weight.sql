-- Allow roster imports to create athletes before their competition weight is known.
ALTER TABLE athletes
    MODIFY COLUMN weight_kg DECIMAL(6,2) NULL;
