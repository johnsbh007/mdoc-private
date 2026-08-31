<?php
/**
 * Migration: ໃບຢັ້ງຢືນລູກທາງ (ລູກຊູ້) — Foster/Adopted Child Certificate (doc_type 1, doc_item 57, code 31)
 * Single-party certificate: applicant profile (name/age/occupation/address) from
 * citizen record; former partner + child + relationship details are EAV.
 * Idempotent.
 */
require __DIR__ . '/../connect.php';

$conn->beginTransaction();

$fields = [
    ['foster_partner_name',       'ຊື່ ຄູ່ຮັກ (Partner name)',               'TEXT'],
    ['foster_partner_age',        'ອາຍຸ ຄູ່ຮັກ (Partner age)',               'TEXT'],
    ['foster_partner_occupation', 'ອາຊີບ ຄູ່ຮັກ (Partner occupation)',      'TEXT'],
    ['foster_partner_unit',       'ໜ່ວຍ ຄູ່ຮັກ (Partner unit)',              'TEXT'],
    ['foster_partner_village',    'ບ້ານ ຄູ່ຮັກ (Partner village)',           'TEXT'],
    ['foster_partner_district',   'ເມືອງ ຄູ່ຮັກ (Partner district)',         'TEXT'],
    ['foster_partner_province',   'ແຂວງ ຄູ່ຮັກ (Partner province)',         'TEXT'],
    ['foster_partner_family_no',  'ສຳມະໂນຄົວ ຄູ່ຮັກ (Partner family book no)', 'TEXT'],
    ['foster_partner_family_date','ສຳມະໂນຄົວ ລົງວັນທີ (Partner family book date)', 'DATE'],
    ['foster_live_since_year',    'ຢູ່ກິນນໍາກັນມາແຕ່ປີ (Lived together since)', 'TEXT'],
    ['foster_children_count',     'ຈຳນວນລູກ (Children count)',              'TEXT'],
    ['foster_child_name',         'ຊື່ລູກ (Child name)',                     'TEXT'],
    ['foster_separated_year',     'ແຍກທາງກັນປີ (Separated year)',           'TEXT'],
    ['foster_purpose',            'ຈຸດປະສົງເຮັດເອກະສານ (Purpose)',       'TEXTAREA'],
];

$inserted = 0; $sort = 1;
foreach ($fields as [$name, $label, $type]) {
    $st = $conn->prepare("SELECT id FROM edoc_doc_field WHERE doc_type_id=1 AND doc_item_id=57 AND field_name=?");
    $st->execute([$name]);
    if (!$st->fetchColumn()) {
        $conn->prepare("INSERT INTO edoc_doc_field (doc_type_id, doc_item_id, field_name, field_label, field_type, is_active, sort_order)
                        VALUES (1, 57, ?, ?, ?, 1, ?)")->execute([$name, $label, $type, $sort]);
        $inserted++;
    }
    $sort++;
}

try { $conn->commit(); } catch (Throwable $e) { /* ALTER TABLE auto-commits */ }
echo "foster-child fields (item 57): $inserted inserted (" . count($fields) . " defined, idempotent)\n";
echo "DONE\n";