-- Show the 2 district mismatches
SELECT s.district_code AS code,
       s.district_name_lao AS tax_ets_lao, HEX(s.district_name_lao) AS tax_ets_hex,
       t.name_lo AS edoc_lao, HEX(t.name_lo) AS edoc_hex,
       s.district_name AS tax_ets_en
FROM tax_ets.districts s
LEFT JOIN edoc_tasaeng.edoc_district t ON t.dist_code = s.district_code COLLATE utf8mb4_unicode_ci
WHERE s.active=1 AND (t.id IS NULL OR HEX(t.name_lo) <> HEX(s.district_name_lao));
