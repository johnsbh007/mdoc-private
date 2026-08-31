<?php
/**
 * Migration: ໃບແຈ້ງເສຍຫາຍ (Damage/Loss Notification, doc_type 4, doc_item 4)
 * Single-party notification: applicant profile (name/DOB/nationality/occupation/
 * address/family book) from citizen record; report date/time + damage description
 * + damage date from EAV. Idempotent.
 */
require __DIR__ . '/../connect.php';

$conn->beginTransaction();

$fields = [
    ['notify_report_date', 'ວັນທີ ແຈ້ງ (Report date)',              'DATE'],
    ['notify_report_time', 'ເວລາ ແຈ້ງ (Report time)',              'TEXT'],
    ['notify_damage_desc', 'ລາຍລະອຽດການເສຍຫາຍ (Damage description)', 'TEXTAREA'],
    ['notify_damage_date', 'ວັນທີ ເສຍຫາຍ (Damage date)',           'DATE'],
];

$inserted = 0; $sort = 1;
foreach ($fields as [$name, $label, $type]) {
    $st = $conn->prepare("SELECT id FROM edoc_doc_field WHERE doc_type_id=4 AND doc_item_id=4 AND field_name=?");
    $st->execute([$name]);
    if (!$st->fetchColumn()) {
        $conn->prepare("INSERT INTO edoc_doc_field (doc_type_id, doc_item_id, field_name, field_label, field_type, is_active, sort_order)
                        VALUES (4, 4, ?, ?, ?, 1, ?)")->execute([$name, $label, $type, $sort]);
        $inserted++;
    }
    $sort++;
}

try { $conn->commit(); } catch (Throwable $e) { /* ALTER TABLE auto-commits */ }
echo "damage-notification fields (item 4): $inserted inserted (" . count($fields) . " defined, idempotent)\n";
echo "DONE\n";