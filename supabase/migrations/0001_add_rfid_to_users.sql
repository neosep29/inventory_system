-- GS-IS: Add RFID number column to users table
-- Run this in phpMyAdmin or MySQL CLI BEFORE using the RFID login feature

ALTER TABLE users
    ADD COLUMN rfid_number VARCHAR(32) NULL DEFAULT NULL
        COMMENT 'RFID tag serial number as emitted by the USB HID reader (normalized: no spaces/dashes, uppercase hex if applicable)',
    ADD UNIQUE KEY idx_users_rfid (rfid_number);

-- Optional helpful notes:
--   VARCHAR(32) accommodates:
--     EM4100 125kHz .......... 10 decimal digits (e.g. "0001234567")
--     Mifare Classic / NTAG .. 4..7 byte UID as 8..14 hex chars (e.g. "A1B2C3D4" or "04A1B2C3D45680")
--     Wiegand-padded variants .. up to ~26 chars
--   UNIQUE constraint prevents assigning the same card to two accounts.
