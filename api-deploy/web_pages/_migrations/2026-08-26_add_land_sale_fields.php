<?php
/**
 * Migration: define form fields for doc_type 14 — ໃບຢັ້ງຢືນຊື້-ຂາຍ ທີ່ດິນ (land sell-purchase letter)
 * Item 52 (200,000 LAK). Template: Doc_files/31 ໃບຢັ້ງຢືນຊື້-ຂາຍ ທີ່ດິນ.docx
 * NOTE: the template filename "31" is a Doc_files catalog number — the item id is 52,
 * under doc_type 14 (ຊື້-ຂາຍ). Item id 31 is ໄກ່ເກ່ຍຂໍ້ຂັດແຍ່ງ and is NOT this form.
 * seller = the applicant (citizen record); only letter-specific fields are stored
 * in edoc_form_data (EAV) keyed by these field ids. Idempotent (INSERT IGNORE by field_name).
 */

require_once __DIR__ . '/../connect.php';

$fields = [
    // ── 3 witnesses ──
    ['witness1_name',     'ພະຍານ 1 ຊື່ (Witness 1 name)',     'TEXT'],
    ['witness1_age',      'ພະຍານ 1 ອາຍຸ (Witness 1 age)',      'TEXT'],
    ['witness1_occupation','ພະຍານ 1 ອາຊີບ (Witness 1 occupation)','TEXT'],
    ['witness1_village',  'ພະຍານ 1 ບ້ານ (Witness 1 village)',  'TEXT'],
    ['witness2_name',     'ພະຍານ 2 ຊື່ (Witness 2 name)',     'TEXT'],
    ['witness2_age',      'ພະຍານ 2 ອາຍຸ (Witness 2 age)',      'TEXT'],
    ['witness2_occupation','ພະຍານ 2 ອາຊີບ (Witness 2 occupation)','TEXT'],
    ['witness2_village',  'ພະຍານ 2 ບ້ານ (Witness 2 village)',  'TEXT'],
    ['witness3_name',     'ພະຍານ 3 ຊື່ (Witness 3 name)',     'TEXT'],
    ['witness3_age',      'ພະຍານ 3 ອາຍຸ (Witness 3 age)',      'TEXT'],
    ['witness3_occupation','ພະຍານ 3 ອາຊີບ (Witness 3 occupation)','TEXT'],
    ['witness3_village',  'ພະຍານ 3 ບ້ານ (Witness 3 village)',  'TEXT'],
    // ── land ──
    ['land_plots',        'ຈຳນວນທີ່ດິນ ກີ່ຕອນ (Land plots count)',  'TEXT'],
    ['land_amount',       'ຈຳນວນເງິນຂາຍ (Sale amount)',           'NUMBER'],
    ['land_area',         'ເນື້ອທີ່ດິນ (Land area)',               'TEXT'],
    // ── borders ──
    ['border_north_neighbor',  'ທິດເໜືອ ຕິດກັບ (North neighbor)',  'TEXT'],
    ['border_north_meters',    'ທິດເໜືອ ໄລຍະ ແມັດ (North meters)',  'TEXT'],
    ['border_south_neighbor',  'ທິດໃຕ້ ຕິດກັບ (South neighbor)',  'TEXT'],
    ['border_south_meters',    'ທິດໃຕ້ ໄລຍະ ແມັດ (South meters)',  'TEXT'],
    ['border_east_neighbor',   'ທິດຕາເວັນອອກ ຕິດກັບ (East neighbor)', 'TEXT'],
    ['border_east_meters',     'ທິດຕາເວັນອອກ ໄລຍະ ແມັດ (East meters)', 'TEXT'],
    ['border_west_neighbor',   'ທິດຕາເວັນຕົກ ຕິດກັບ (West neighbor)', 'TEXT'],
    ['border_west_meters',     'ທິດຕາເວັນຕົກ ໄລຍະ ແມັດ (West meters)', 'TEXT'],
    // ── buyer ──
    ['buyer_name',        'ຊື່ ຜູ້ຊື້ (Buyer name)',          'TEXT'],
    ['buyer_age',         'ອາຍຸ ຜູ້ຊື້ (Buyer age)',           'TEXT'],
    ['buyer_nationality', 'ສັນຊາດ ຜູ້ຊື້ (Buyer nationality)',  'TEXT'],
    ['buyer_ethnicity',   'ຊົນເຜົ່າ ຜູ້ຊື້ (Buyer ethnicity)',  'TEXT'],
    ['buyer_religion',    'ສາສະໜາ ຜູ້ຊື້ (Buyer religion)',    'TEXT'],
    ['buyer_occupation',  'ອາຊີບ ຜູ້ຊື້ (Buyer occupation)',   'TEXT'],
    ['buyer_unit',        'ໜ່ວຍ ຜູ້ຊື້ (Buyer unit)',          'TEXT'],
    ['buyer_village',     'ບ້ານ ຜູ້ຊື້ (Buyer village)',       'TEXT'],
    ['buyer_district',    'ເມືອງ ຜູ້ຊື້ (Buyer district)',     'TEXT'],
    ['buyer_province',    'ແຂວງ ຜູ້ຊື້ (Buyer province)',      'TEXT'],
    ['buyer_family_book','ສຳມະໂນຄົວເລກທີ ຜູ້ຊື້ (Buyer family book no)', 'TEXT'],
    ['buyer_id_card',     'ບັດປະຈຳຕົວເລກທີ ຜູ້ຊື້ (Buyer ID card)', 'TEXT'],
    ['buyer_phone',       'ເບີໂທ ຜູ້ຊື້ (Buyer phone)',        'TEXT'],
];

$conn->beginTransaction();
$inserted = 0; $sort = 1;
foreach ($fields as [$name, $label, $type]) {
    // unique by (doc_type_id, field_name)
    $st = $conn->prepare("SELECT id FROM edoc_doc_field WHERE doc_type_id=14 AND field_name=?");
    $st->execute([$name]);
    if (!$st->fetchColumn()) {
        $conn->prepare("INSERT INTO edoc_doc_field (doc_type_id, field_name, field_label, field_type, is_active, sort_order)
                        VALUES (14, ?, ?, ?, 1, ?)")->execute([$name, $label, $type, $sort]);
        $inserted++;
    }
    $sort++;
}
$conn->commit();
echo "doc_type 14 fields: $inserted inserted (" . count($fields) . " defined, idempotent)\n";
echo "DONE\n";