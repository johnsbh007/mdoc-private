<?php
/**
 * Migration: ໃບຢັ້ງຢືນຄວາມເປັນໂສດ (Single Status Certificate, doc_type 1, doc_item 56, code 30)
 * Single-party certificate: applicant profile from citizen record; father + mother
 * are EAV (not second-party citizens). Issued-by authority is also EAV.
 * Idempotent.
 */
require __DIR__ . '/../connect.php';

$conn->beginTransaction();

$fields = [
    ['single_issued_by',      'ອອກໃຫ້ໂດຍ (Issued by authority)',     'TEXT'],
    ['single_father_name',    'ເປັນລູກຂອງທ້າວ (Father name)',       'TEXT'],
    ['single_mother_name',    'ແລະ ນາງ (Mother name)',                'TEXT'],
    ['single_parent_village', 'ຢູ່ບ້ານ (Parents village)',              'TEXT'],
    ['single_parent_district','ເມືອງ (Parents district)',            'TEXT'],
    ['single_parent_province','ແຂວງ (Parents province)',            'TEXT'],
    ['single_family_book_no',   'ສຳມະໂນຄົວເລກທີ (Family book no)',   'TEXT'],
    ['single_family_book_date', 'ສຳມະໂນຄົວ ລົງວັນທີ (Family book date)','DATE'],
];

$inserted = 0; $sort = 1;
foreach ($fields as [$name, $label, $type]) {
    $st = $conn->prepare("SELECT id FROM edoc_doc_field WHERE doc_type_id=1 AND doc_item_id=56 AND field_name=?");
    $st->execute([$name]);
    if (!$st->fetchColumn()) {
        $conn->prepare("INSERT INTO edoc_doc_field (doc_type_id, doc_item_id, field_name, field_label, field_type, is_active, sort_order)
                        VALUES (1, 56, ?, ?, ?, 1, ?)")->execute([$name, $label, $type, $sort]);
        $inserted++;
    }
    $sort++;
}

try { $conn->commit(); } catch (Throwable $e) { /* ALTER TABLE auto-commits */ }
echo "single-status fields (item 56): $inserted inserted (" . count($fields) . " defined, idempotent)\n";
echo "DONE\n";