UPDATE clubs
SET phone = contact_phone
WHERE phone = '' AND contact_phone <> '';

ALTER TABLE clubs
    DROP COLUMN contact_phone,
    DROP COLUMN contact_email,
    DROP COLUMN recovery_email,
    ADD COLUMN address_line VARCHAR(255) NULL AFTER phone,
    ADD COLUMN postal_code VARCHAR(20) NULL AFTER address_line,
    ADD COLUMN city VARCHAR(120) NOT NULL DEFAULT '' AFTER postal_code,
    ADD COLUMN province VARCHAR(120) NOT NULL DEFAULT '' AFTER city;
