<?php
/**
 * Migration: ໃບຢັ້ງຢືນການເສຍຫາຍ (Damage/Loss Certificate, doc_type 1, doc_item 55)
 * Single-party certificate: applicant citizen record provides the full profile
 * (name/DOB/origin/nationality/ethnicity/religion/occupation/address/family book).
 * Only the damage description is captured as EAV.
 * Idempotent.
 */
require __DIR__ . '/../connect.php';

$conn->beginTransaction();

$fields = [
    ['damage_desc', 'ລາຍລະອຽດການເສຍຫາຍ (Damage description)', 'TEXTAREA'],
];

$inserted = 0; $sort = 1;
foreach ($fields as [$name, $label, $type]) {
    $st = $conn->prepare("SELECT id FROM edoc_doc_field WHERE doc_type_id=1 AND doc_item_id=55 AND field_name=?");
    $st->execute([$name]);
    if (!$st->fetchColumn()) {
        $conn->prepare("INSERT INTO edoc_doc_field (doc_type_id, doc_item_id, field_name, field_label, field_type, is_active, sort_order)
                        VALUES (1, 55, ?, ?, ?, 1, ?)")->execute([$name, $label, $type, $sort]);
        $inserted++;
    }
    $sort++;
}

try { $conn->commit(); } catch (Throwable $e) { /* ALTER TABLE auto-commits */ }
echo "damage certificate fields (item 55): $inserted inserted (" . count($fields) . " defined, idempotent)\n";
echo "DONE\n";