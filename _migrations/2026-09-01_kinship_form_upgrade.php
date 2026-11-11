<?php
// Kinship (item 5) form upgrade migration:
// 1) deactivate ອອກໃຫ້ໂດຍ (kin_issued_by, field 50) — user says remove (not wanted in form)
// 2) mark *_occupation fields as OCCUPATION (dropdown from edoc_occupation)
// 3) mark *_village fields as VILLAGE (cascading province→district→taseng→village)
$pdo = new PDO("mysql:host=localhost;dbname=edoc_tasaeng;charset=utf8mb4", "admin", "Sql_admin@#2024");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1) kin_issued_by -> inactive
$pdo->exec("UPDATE edoc_doc_field SET is_active=0 WHERE id=50 AND doc_item_id=5");
echo "kin_issued_by deactivated\n";

// 1b) spouse_district/spouse_province (77,78) -> inactive; the village cascade
//     derives ເມືອງ/ແຂວງ from the village's parent chain (option 1 storage).
$pdo->exec("UPDATE edoc_doc_field SET is_active=0 WHERE id IN (77,78) AND doc_item_id=5");
echo "spouse_district/spouse_province deactivated (derived from village cascade)\n";

// 2) occupation fields
$n = $pdo->exec("UPDATE edoc_doc_field SET field_type='OCCUPATION' WHERE doc_item_id=5 AND field_name LIKE '%_occupation' AND is_active=1");
echo "occupation fields updated: $n\n";

// 3) village fields
$n = $pdo->exec("UPDATE edoc_doc_field SET field_type='VILLAGE' WHERE doc_item_id=5 AND field_name LIKE '%_village' AND is_active=1");
echo "village fields updated: $n\n";

// verify
foreach ($pdo->query("SELECT field_name, field_type, is_active FROM edoc_doc_field WHERE doc_item_id=5 ORDER BY sort_order") as $r) {
    echo $r['field_name'] . "\t" . $r['field_type'] . "\tis_active=" . $r['is_active'] . "\n";
}