-- Fix 2 district names in edoc_tasaeng (they were wrongly seeded with village names)
SET NAMES utf8mb4;
UPDATE edoc_district SET name_lo = 'ຮົ່ມ', name_en = 'Hom' WHERE dist_code = '1804';
UPDATE edoc_district SET name_lo = 'ລ້ອງຊານ', name_en = 'Longxan' WHERE dist_code = '1805';
-- Verify
SELECT dist_code, name_lo, name_en FROM edoc_district WHERE dist_code IN ('1804','1805');
