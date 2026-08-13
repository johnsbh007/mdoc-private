-- Extend edoc_citizen with first_name / last_name / address_line (residence already via village_id)
-- Run against edoc_tasaeng on apis.com.la
SET NAMES utf8mb4;

ALTER TABLE edoc_citizen
  ADD COLUMN first_name varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Given name' AFTER full_name,
  ADD COLUMN last_name  varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Family name' AFTER first_name,
  ADD COLUMN address_line varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Free-text address (house no, street, etc.)' AFTER village_id;

-- Backfill full_name = first_name + ' ' + last_name for any existing rows (none expected)
UPDATE edoc_citizen SET full_name = TRIM(CONCAT(first_name, ' ', last_name)) WHERE full_name = '' OR full_name IS NULL;
