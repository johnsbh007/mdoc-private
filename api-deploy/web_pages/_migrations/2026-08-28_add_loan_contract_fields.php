<?php
/**
 * Migration: ສັນຍາກູ້ຢືມເງິນ (Loan Contract, doc_type 6, doc_item 28)
 * Creates item-28-specific contract terms fields. Reuses buyer_id (already on
 * edoc_document from the land-sale feature) for the second party (borrower).
 * Idempotent.
 */
require __DIR__ . '/../connect.php';

$conn->beginTransaction();

$fields = [
    // Contract terms
    ['loan_amount',       'ຈຳນວນເງິນກູ້ (Loan amount)',                      'TEXT'],
    ['loan_amount_words', 'ຈຳນວນເງິນ (ຕົວໜັງສື) (Amount in words)',         'TEXT'],
    ['loan_start_date',   'ວັນທີເລີ່ມກູ້ (Start date)',                       'DATE'],
    ['loan_end_date',     'ວັນທີສິ້ນສຸດ (End date)',                          'DATE'],
    ['loan_months',       'ຈຳນວນເດືອນ (Months)',                               'TEXT'],
    ['loan_interest_pct', 'ດອກເບ້ຍ % ຕໍ່ເດືອນ (Interest % per month)',         'TEXT'],
    ['loan_note',         'ໝາຍເຫດ (Notes)',                                     'TEXTAREA'],
    // Party A family book (for the applicant / lender citizen)
    ['party_a_family_book_no',   'ຂື້ນສຳມະໂນຄົວເລກທີ ຝ່າຍກໍ (Party A family book no)', 'TEXT'],
    ['party_a_family_book_date', 'ລົງວັນທີ ສຳມະໂນຄົວ ຝ່າຍກໍ (Party A family book date)', 'DATE'],
    // Party B family book (for the borrower citizen)
    ['party_b_family_book_no',   'ຂື້ນສຳມະໂນຄົວເລກທີ ຝ່າຍຂໍ (Party B family book no)', 'TEXT'],
    ['party_b_family_book_date', 'ລົງວັນທີ ສຳມະໂນຄົວ ຝ່າຍຂໍ (Party B family book date)', 'DATE'],
];

$inserted = 0; $sort = 1;
foreach ($fields as [$name, $label, $type]) {
    $st = $conn->prepare("SELECT id FROM edoc_doc_field WHERE doc_type_id=6 AND doc_item_id=28 AND field_name=?");
    $st->execute([$name]);
    if (!$st->fetchColumn()) {
        $conn->prepare("INSERT INTO edoc_doc_field (doc_type_id, doc_item_id, field_name, field_label, field_type, is_active, sort_order)
                        VALUES (6, 28, ?, ?, ?, 1, ?)")->execute([$name, $label, $type, $sort]);
        $inserted++;
    }
    $sort++;
}

try { $conn->commit(); } catch (Throwable $e) { /* ALTER TABLE auto-commits */ }
echo "loan contract fields (item 28): $inserted inserted (" . count($fields) . " defined, idempotent)\n";
echo "DONE\n";