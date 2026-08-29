<?php
/**
 * Migration: ໃບຍົກຍ້າຍ (Relocation Certificate, doc_type 1, doc_item 28)
 * Creates item-28-specific EAV fields. The person being transferred = applicant
 * citizen (name/DOB from citizen record); the rest (recipient, subject, legal
 * basis, organization, position, grades, education, from/to) are EAV.
 * Idempotent.
 */
require __DIR__ . '/../connect.php';

$conn->beginTransaction();

$fields = [
    ['relocate_recipient',   'ຮຽນ (To / Recipient)',                              'TEXT'],
    ['relocate_subject',     'ເລື່ອງ (Subject)',                                 'TEXT'],
    ['relocate_basis1',      'ອີງຕາມ 1 (Legal basis 1)',                        'TEXT'],
    ['relocate_basis1_detail','ອີງຕາມ 1 ລາຍລະອຽດ (Basis 1 detail)',           'TEXTAREA'],
    ['relocate_basis2',      'ອີງຕາມ 2 (Legal basis 2)',                        'TEXT'],
    ['relocate_basis2_detail','ອີງຕາມ 2 ລາຍລະອຽດ (Basis 2 detail)',          'TEXTAREA'],
    ['relocate_org_name',    'ຊື່ອົງການຈັດຕັ້ງ (Organization name)',          'TEXT'],
    ['relocate_position',    'ຕຳແໜ່ງ (Position)',                               'TEXT'],
    ['relocate_years',       'ປີການ (Years of service)',                         'TEXT'],
    ['relocate_grade',       'ຊັ້ນ (Salary grade)',                               'TEXT'],
    ['relocate_salary',      'ຂັ້ນເງິນເດືອນ (Salary level)',                  'TEXT'],
    ['relocate_education',   'ວຸດທິການສຶກສາ (Education)',                    'TEXT'],
    ['relocate_from',        'ຈາກ (From)',                                        'TEXT'],
    ['relocate_to',          'ໄປປະຈໍາຢູ່ (To place)',                          'TEXT'],
];

$inserted = 0; $sort = 1;
foreach ($fields as [$name, $label, $type]) {
    $st = $conn->prepare("SELECT id FROM edoc_doc_field WHERE doc_type_id=1 AND doc_item_id=54 AND field_name=?");
    $st->execute([$name]);
    if (!$st->fetchColumn()) {
        $conn->prepare("INSERT INTO edoc_doc_field (doc_type_id, doc_item_id, field_name, field_label, field_type, is_active, sort_order)
                        VALUES (1, 54, ?, ?, ?, 1, ?)")->execute([$name, $label, $type, $sort]);
        $inserted++;
    }
    $sort++;
}

try { $conn->commit(); } catch (Throwable $e) { /* ALTER TABLE auto-commits */ }
echo "relocation fields (item 28): $inserted inserted (" . count($fields) . " defined, idempotent)\n";
echo "DONE\n";