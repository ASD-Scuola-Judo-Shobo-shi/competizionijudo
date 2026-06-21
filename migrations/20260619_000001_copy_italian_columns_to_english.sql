-- One-way migration: copy legacy Italian column values into new English columns.
-- Idempotent: runs safely multiple times, only copies when target column is empty.

START TRANSACTION;

-- Clubs
UPDATE clubs SET federal_code = codice_federale
 WHERE (federal_code IS NULL OR federal_code = '')
   AND (codice_federale IS NOT NULL AND codice_federale <> '');

UPDATE clubs SET name = nome_societa
 WHERE (name IS NULL OR name = '')
   AND (nome_societa IS NOT NULL AND nome_societa <> '');

UPDATE clubs SET email = email_societa
 WHERE (email IS NULL OR email = '')
   AND (email_societa IS NOT NULL AND email_societa <> '');

UPDATE clubs SET phone = telefono_societa
 WHERE (phone IS NULL OR phone = '')
   AND (telefono_societa IS NOT NULL AND telefono_societa <> '');

UPDATE clubs SET contact_first_name = nome_referente
 WHERE (contact_first_name IS NULL OR contact_first_name = '')
   AND (nome_referente IS NOT NULL AND nome_referente <> '');

UPDATE clubs SET contact_last_name = cognome_referente
 WHERE (contact_last_name IS NULL OR contact_last_name = '')
   AND (cognome_referente IS NOT NULL AND cognome_referente <> '');

UPDATE clubs SET contact_phone = telefono_referente
 WHERE (contact_phone IS NULL OR contact_phone = '')
   AND (telefono_referente IS NOT NULL AND telefono_referente <> '');

UPDATE clubs SET contact_email = email_referente
 WHERE (contact_email IS NULL OR contact_email = '')
   AND (email_referente IS NOT NULL AND email_referente <> '');

UPDATE clubs SET organization = ente
 WHERE (organization IS NULL OR organization = '')
   AND (ente IS NOT NULL AND ente <> '');

UPDATE clubs SET recovery_email = email_recupero
 WHERE (recovery_email IS NULL OR recovery_email = '')
   AND (email_recupero IS NOT NULL AND email_recupero <> '');

-- Events
UPDATE events SET name = nome_evento
 WHERE (name IS NULL OR name = '')
   AND (nome_evento IS NOT NULL AND nome_evento <> '');

-- Try to convert common date formats into DATE column when possible
UPDATE events SET date = STR_TO_DATE(data_gara, '%Y-%m-%d')
 WHERE (date IS NULL OR date = '0000-00-00')
   AND data_gara REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$';

UPDATE events SET date = STR_TO_DATE(data_gara, '%d/%m/%Y')
 WHERE (date IS NULL OR date = '0000-00-00')
   AND data_gara REGEXP '^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}$';

UPDATE events SET date = NULLIF(data_gara, '')
 WHERE (date IS NULL OR date = '0000-00-00')
   AND (data_gara IS NOT NULL AND data_gara <> '');

UPDATE events SET location = luogo
 WHERE (location IS NULL OR location = '')
   AND (luogo IS NOT NULL AND luogo <> '');

UPDATE events SET organizer = organizzatore
 WHERE (organizer IS NULL OR organizer = '')
   AND (organizzatore IS NOT NULL AND organizzatore <> '');

UPDATE events SET registration_deadline = STR_TO_DATE(scadenza_iscrizioni, '%Y-%m-%d')
 WHERE (registration_deadline IS NULL OR registration_deadline = '0000-00-00')
   AND scadenza_iscrizioni REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$';

UPDATE events SET registration_deadline = STR_TO_DATE(scadenza_iscrizioni, '%d/%m/%Y')
 WHERE (registration_deadline IS NULL OR registration_deadline = '0000-00-00')
   AND scadenza_iscrizioni REGEXP '^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}$';

UPDATE events SET registration_deadline = NULLIF(scadenza_iscrizioni, '')
 WHERE (registration_deadline IS NULL OR registration_deadline = '0000-00-00')
   AND (scadenza_iscrizioni IS NOT NULL AND scadenza_iscrizioni <> '');

UPDATE events SET type = tipo_evento
 WHERE (type IS NULL OR type = '')
   AND (tipo_evento IS NOT NULL AND tipo_evento <> '');

UPDATE events SET description = descrizione
 WHERE (description IS NULL OR description = '')
   AND (descrizione IS NOT NULL AND descrizione <> '');

UPDATE events SET notes = note
 WHERE (notes IS NULL OR notes = '')
   AND (note IS NOT NULL AND note <> '');

UPDATE events SET poster_file = poster_file
 WHERE (poster_file IS NULL OR poster_file = '')
   AND (poster_file IS NOT NULL AND poster_file <> '');

UPDATE events SET info_file = info_file
 WHERE (info_file IS NULL OR info_file = '')
   AND (info_file IS NOT NULL AND info_file <> '');

UPDATE events SET published = GREATEST(COALESCE(published,0), COALESCE(pubblicato,0));
UPDATE events SET closed = GREATEST(COALESCE(closed,0), COALESCE(chiuso,0));

-- Athletes
UPDATE athletes SET last_name = cognome
 WHERE (last_name IS NULL OR last_name = '')
   AND (cognome IS NOT NULL AND cognome <> '');

UPDATE athletes SET first_name = nome
 WHERE (first_name IS NULL OR first_name = '')
   AND (nome IS NOT NULL AND nome <> '');

UPDATE athletes SET gender = sesso
 WHERE (gender IS NULL OR gender = '')
   AND (sesso IS NOT NULL AND sesso <> '');

UPDATE athletes SET date_of_birth = STR_TO_DATE(nascita, '%Y-%m-%d')
 WHERE (date_of_birth IS NULL OR date_of_birth = '0000-00-00')
   AND nascita REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$';

UPDATE athletes SET date_of_birth = STR_TO_DATE(nascita, '%d/%m/%Y')
 WHERE (date_of_birth IS NULL OR date_of_birth = '0000-00-00')
   AND nascita REGEXP '^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}$';

UPDATE athletes SET date_of_birth = NULLIF(nascita, '')
 WHERE (date_of_birth IS NULL OR date_of_birth = '0000-00-00')
   AND (nascita IS NOT NULL AND nascita <> '');

-- For numeric weight columns prefer any existing new value, otherwise copy old numeric column
UPDATE athletes SET weight_kg = COALESCE(NULLIF(weight_kg, 0), NULLIF(actual_weight_kg, 0), peso_reale_kg)
 WHERE (weight_kg IS NULL OR weight_kg = 0)
   AND (COALESCE(actual_weight_kg, peso_reale_kg) IS NOT NULL);

UPDATE athletes SET belt = cintura
 WHERE (belt IS NULL OR belt = '')
   AND (cintura IS NOT NULL AND cintura <> '');

UPDATE athletes SET age_class = classe_eta
 WHERE (age_class IS NULL OR age_class = '')
   AND (classe_eta IS NOT NULL AND classe_eta <> '');

UPDATE athletes SET program = programma
 WHERE (program IS NULL OR program = '')
   AND (programma IS NOT NULL AND programma <> '');

UPDATE athletes SET weight_category = categoria_peso
 WHERE (weight_category IS NULL OR weight_category = '')
   AND (categoria_peso IS NOT NULL AND categoria_peso <> '');

UPDATE athletes SET membership_number = numero_tessera
 WHERE (membership_number IS NULL OR membership_number = '')
   AND (numero_tessera IS NOT NULL AND numero_tessera <> '');

UPDATE athletes SET notes = note
 WHERE (notes IS NULL OR notes = '')
   AND (note IS NOT NULL AND note <> '');

COMMIT;
