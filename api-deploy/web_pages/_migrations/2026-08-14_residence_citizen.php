<?php
// Migration: extend edoc_citizen (reusable personal fields) + create edoc_dc_residence (cert-specific store)
// + repopulate edoc_doc_field for doc_type_id=1 (Residence Certification).
// Run once on the server, then delete.
require '/var/www/html/edoc/connect.php';
$conn->exec("SET NAMES utf8mb4");

$sql = "
ALTER TABLE edoc_citizen
  ADD COLUMN nationality VARCHAR(100) DEFAULT NULL AFTER dob,
  ADD COLUMN origin VARCHAR(100) DEFAULT NULL AFTER nationality,
  ADD COLUMN ethnicity VARCHAR(100) DEFAULT NULL AFTER origin,
  ADD COLUMN religion VARCHAR(100) DEFAULT NULL AFTER ethnicity,
  ADD COLUMN occupation VARCHAR(150) DEFAULT NULL AFTER religion,
  ADD COLUMN birth_village VARCHAR(150) DEFAULT NULL AFTER occupation,
  ADD COLUMN birth_district VARCHAR(150) DEFAULT NULL AFTER birth_village,
  ADD COLUMN birth_province VARCHAR(150) DEFAULT NULL AFTER birth_district,
  ADD COLUMN id_card_issue_date DATE DEFAULT NULL AFTER birth_province,
  ADD COLUMN house_no VARCHAR(20) DEFAULT NULL AFTER id_card_issue_date,
  ADD COLUMN unit_no VARCHAR(20) DEFAULT NULL AFTER house_no;

CREATE TABLE IF NOT EXISTS edoc_dc_residence (
  doc_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  household_book_no VARCHAR(50) DEFAULT NULL,
  household_book_date DATE DEFAULT NULL,
  father_name VARCHAR(150) DEFAULT NULL,
  mother_name VARCHAR(150) DEFAULT NULL,
  parents_address VARCHAR(255) DEFAULT NULL,
  related_authority VARCHAR(200) DEFAULT NULL,
  purpose TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (doc_id) REFERENCES edoc_document(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";
$conn->exec($sql);
echo "SCHEMA_OK\n";

// Repopulate edoc_doc_field for doc_type_id=1 with the cert-specific fields (7 rows).
$conn->prepare("DELETE FROM edoc_doc_field WHERE doc_type_id = 1")->execute();
$fields = [
  ['household_book_no', 'ເລກທີໜັງສືສຳມະໂນຄົວ', 'TEXT', 1],
  ['household_book_date', 'ວັນທີລົງທະບຽນສຳມະໂນຄົວ', 'DATE', 2],
  ['father_name', 'ຊື່ ພໍ່', 'TEXT', 3],
  ['mother_name', 'ຊື່ ແມ່', 'TEXT', 4],
  ['parents_address', 'ທີ່ຢູ່ຂອງພໍ່ແມ່', 'TEXT', 5],
  ['related_authority', 'ໜ່ວຍງານທີ່ກ່ຽວຂ້ອງ', 'TEXT', 6],
  ['purpose', 'ຈຸດປະສົງ', 'TEXTAREA', 7],
];
$ins = $conn->prepare("INSERT INTO edoc_doc_field (doc_type_id, field_name, field_label, field_type, is_active, sort_order) VALUES (1,?,?,?,1,?)");
foreach ($fields as $f) { $ins->execute($f); }
echo "FIELDS_OK count=" . count($fields) . "\n";

// Verify
$chk = $conn->query("SHOW COLUMNS FROM edoc_citizen LIKE 'nationality'")->fetchColumn();
$cnt = $conn->query("SELECT COUNT(*) FROM edoc_doc_field WHERE doc_type_id=1")->fetchColumn();
echo "citizen_nationality_col=" . ($chk ? 'yes' : 'no') . " doc_field_rows=$cnt\n";
