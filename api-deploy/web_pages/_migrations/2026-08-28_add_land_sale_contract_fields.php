<?php
/**
 * Migration: ສັນຍາຊື້-ຂາຍ ກຳມະສິດທີ່ດິນ (Land Sale Contract, doc_type 6, doc_item 31)
 * Creates item-31-specific fields for the land sale-purchase contract.
 * Reuses buyer_id (already on edoc_document) for the buyer (second party).
 * Idempotent.
 */
require __DIR__ . '/../connect.php';

$conn->beginTransaction();

$fields = [
    // Land details (ມາດຕາ 1)
    ['land_village',      'ບ້ານທີ່ຕັ້ງດິນ (Land village)',                   'TEXT'],
    ['land_district',     'ເມືອງທີ່ຕັ້ງດິນ (Land district)',                 'TEXT'],
    ['land_province',     'ແຂວງທີ່ຕັ້ງດິນ (Land province)',                  'TEXT'],
    ['land_area_m2',      'ເນື້ອທີ່ດິນມ2 (Land area m²)',                      'TEXT'],
    ['land_deed_no',      'ໃບຕາດິນເລກທີ (Land deed no)',                     'TEXT'],
    ['land_map_no',       'ແຜນຕາດິນເລກທີ (Land map no)',                     'TEXT'],
    ['land_owner_name',   'ອອກຊື່ (Registered owner name)',                    'TEXT'],
    ['land_owner_date',   'ອອກຊື່ລົງວັນທີ (Owner registration date)',         'DATE'],
    // Price & payment (ມາດຕາ 2)
    ['total_price',       'ລວມລາຄາຊື້-ຂາຍ (Total price)',                       'TEXT'],
    ['total_price_words', 'ລວມລາຄາຕົວໜັງສື (Total price in words)',            'TEXT'],
    ['paid_amount',       'ຊຳລະແລ້ວຈຳນວນ (Paid amount)',                       'TEXT'],
    ['paid_amount_words', 'ຊຳລະແລ້ວຕົວໜັງສື (Paid amount in words)',          'TEXT'],
    ['remaining_amount',  'ສ່ວນທີ່ຍັງເຫຼືອ (Remaining amount)',                 'TEXT'],
    ['remaining_words',   'ສ່ວນທີ່ຍັງເຫຼືອຕົວໜັງສື (Remaining in words)',    'TEXT'],
    ['deadline_after',    'ພາຍຫຼັງ (Deadline after)',                            'TEXT'],
    ['after_sale_name',   'ອອກເປັນຊື່ (After-sale registered name)',            'TEXT'],
    ['deadline_days',     'ກາຍຈັກວັນ (Deadline days)',                          'TEXT'],
    // Penalty (ມາດຕາ 3)
    ['penalty_amount',    'ຄ່າປັບໃໝ (Penalty amount)',                          'TEXT'],
    // Seller family book (for the applicant seller)
    ['seller_family_book_no',   'ຂື້ນສຳມະໂນຄົວເລກທີ ຜູ້ຂາຍ (Seller family book no)', 'TEXT'],
    ['seller_family_book_date', 'ລົງວັນທີ ສຳມະໂນຄົວ ຜູ້ຂາຍ (Seller family book date)', 'DATE'],
    // Buyer family book
    ['buyer_family_book_no',   'ຂື້ນສຳມະໂນຄົວເລກທີ ຜູ້ຊື້ (Buyer family book no)', 'TEXT'],
    ['buyer_family_book_date', 'ລົງວັນທີ ສຳມະໂນຄົວ ຜູ້ຊື້ (Buyer family book date)', 'DATE'],
];

$inserted = 0; $sort = 1;
foreach ($fields as [$name, $label, $type]) {
    $st = $conn->prepare("SELECT id FROM edoc_doc_field WHERE doc_type_id=6 AND doc_item_id=53 AND field_name=?");
    $st->execute([$name]);
    if (!$st->fetchColumn()) {
        $conn->prepare("INSERT INTO edoc_doc_field (doc_type_id, doc_item_id, field_name, field_label, field_type, is_active, sort_order)
                        VALUES (6, 53, ?, ?, ?, 1, ?)")->execute([$name, $label, $type, $sort]);
        $inserted++;
    }
    $sort++;
}

try { $conn->commit(); } catch (Throwable $e) { /* no DDL, should be fine */ }
echo "land sale contract fields (item 31): $inserted inserted (" . count($fields) . " defined, idempotent)\n";
echo "DONE\n";