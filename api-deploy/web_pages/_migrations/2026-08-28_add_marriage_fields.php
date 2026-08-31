<?php
/**
 * Migration: ໃບຄຳຮ້ອງ ຂໍຈົດທະບຽນແຕ່ງດອງ (Marriage Registration Request, doc_type 2, doc_item 20)
 * Two-party request: groom = applicant citizen; bride = buyer citizen (buyer_id).
 * Recipient, engagement-note ref, and family-book details are EAV (no citizen column).
 * Idempotent.
 */
require __DIR__ . '/../connect.php';

$conn->beginTransaction();

$fields = [
    ['marry_recipient',    'ຮຽນ ທ່ານ (To / Recipient)',                    'TEXT'],
    ['marry_sou_khor_no',  'ບົດບັນທຶກສູ່ຂໍ ສະບັບເລກທີ (Engagement note no)', 'TEXT'],
    ['marry_sou_khor_date','ບົດບັນທຶກສູ່ຂໍ ລົງວັນທີ (Engagement note date)', 'DATE'],
    ['marry_family_book_no',   'ສຳມະໂນຄົວ ກຳມະກອນ (Groom family book no)',  'TEXT'],
    ['marry_family_book_date', 'ສຳມະໂນຄົວ ລົງວັນທີ (Groom family book date)', 'DATE'],
    ['marry_bride_family_book_no',   'ສຳມະໂນຄົວ ເຈົ້າສາວ (Bride family book no)',  'TEXT'],
    ['marry_bride_family_book_date', 'ສຳມະໂນຄົວ ລົງວັນທີ (Bride family book date)', 'DATE'],
];

$inserted = 0; $sort = 1;
foreach ($fields as [$name, $label, $type]) {
    $st = $conn->prepare("SELECT id FROM edoc_doc_field WHERE doc_type_id=2 AND doc_item_id=20 AND field_name=?");
    $st->execute([$name]);
    if (!$st->fetchColumn()) {
        $conn->prepare("INSERT INTO edoc_doc_field (doc_type_id, doc_item_id, field_name, field_label, field_type, is_active, sort_order)
                        VALUES (2, 20, ?, ?, ?, 1, ?)")->execute([$name, $label, $type, $sort]);
        $inserted++;
    }
    $sort++;
}

try { $conn->commit(); } catch (Throwable $e) { /* ALTER TABLE auto-commits */ }
echo "marriage request fields (item 20): $inserted inserted (" . count($fields) . " defined, idempotent)\n";
echo "DONE\n";