-- Cross-check: tax_ets source vs edoc_tasaeng sync
-- Join on codes with explicit collation (tax_ets uses general_ci, edoc uses unicode_ci)
SELECT 'province_name_mismatch' AS check_name, COUNT(*) AS issues
FROM tax_ets.provinces s
LEFT JOIN edoc_tasaeng.edoc_province t ON t.prov_code = s.province_code COLLATE utf8mb4_unicode_ci
WHERE s.active=1 AND (t.id IS NULL OR HEX(t.name_lo) <> HEX(s.province_name_lao));

SELECT 'district_name_mismatch' AS check_name, COUNT(*) AS issues
FROM tax_ets.districts s
LEFT JOIN edoc_tasaeng.edoc_district t ON t.dist_code = s.district_code COLLATE utf8mb4_unicode_ci
WHERE s.active=1 AND (t.id IS NULL OR HEX(t.name_lo) <> HEX(s.district_name_lao));

SELECT 'village_name_mismatch' AS check_name, COUNT(*) AS issues
FROM tax_ets.villages s
LEFT JOIN edoc_tasaeng.edoc_village t ON t.vil_code = s.village_code COLLATE utf8mb4_unicode_ci
WHERE s.active=1 AND (t.id IS NULL OR HEX(t.name_lo) <> HEX(s.village_name_lao));

SELECT 'source_villages_not_synced' AS check_name, COUNT(*) AS issues
FROM tax_ets.villages s
LEFT JOIN edoc_tasaeng.edoc_village t ON t.vil_code = s.village_code COLLATE utf8mb4_unicode_ci
WHERE s.active=1 AND t.id IS NULL;
