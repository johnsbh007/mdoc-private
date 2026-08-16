<?php
// Migration: add geo-code columns to edoc_citizen for 4-level cascading addresses.
// Current address: province_id, district_id, taseng_id, village_id (village_id existed).
// Birth place: birth_province_id, birth_district_id, birth_taseng_id, birth_village_id.
// Store CODE (geo id), show NAME via dropdown joins.
require __DIR__ . '/../connect.php';
$conn->exec("SET NAMES utf8mb4");
try {
  $conn->exec("ALTER TABLE edoc_citizen
    ADD COLUMN province_id BIGINT UNSIGNED NULL AFTER village_id,
    ADD COLUMN district_id BIGINT UNSIGNED NULL AFTER province_id,
    ADD COLUMN taseng_id BIGINT UNSIGNED NULL AFTER district_id,
    ADD COLUMN birth_province_id BIGINT UNSIGNED NULL AFTER birth_province,
    ADD COLUMN birth_district_id BIGINT UNSIGNED NULL AFTER birth_province_id,
    ADD COLUMN birth_taseng_id BIGINT UNSIGNED NULL AFTER birth_district_id,
    ADD COLUMN birth_village_id BIGINT UNSIGNED NULL AFTER birth_taseng_id");
  echo "SCHEMA_OK edoc_citizen geo columns added\n";
} catch (Exception $e) {
  // columns may already exist (re-run) — report but don't fail hard
  if (strpos($e->getMessage(), 'Duplicate column') !== false) {
    echo "ALREADY_EXISTS (re-run safe)\n";
  } else {
    echo "ERR: " . $e->getMessage() . "\n";
  }
}
