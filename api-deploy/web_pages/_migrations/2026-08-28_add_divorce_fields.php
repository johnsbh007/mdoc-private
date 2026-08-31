<?php
/**
 * Migration: ບົດບັນທຶກການຢ່າຮ້າງ (Divorce Record, doc_type 5, doc_item 58, code 26)
 * Two-party memo: husband = applicant citizen (ໂຈດ/plaintiff); wife = buyer
 * citizen (ຈຳເລີຍ/defendant). Memo-specific terms (education-memo ref, meeting,
 * mediation, children, custody, assets/debts) are EAV. Idempotent.
 */
require __DIR__ . '/../connect.php';

$conn->beginTransaction();

$fields = [
    ['divorce_edu_no',     'ບົດບັນທຶກການສຶກສາອົບຮົມຄູ່ຜົວ-ເມຍ ສະບັບເລກທີ (Education memo no)', 'TEXT'],
    ['divorce_edu_date',   'ບົດບັນທຶກ ລົງວັນທີ (Education memo date)',          'DATE'],
    ['divorce_meet_village','ທີ່ຫ້ອງການບ້ານ (Meeting village)',                  'TEXT'],
    ['divorce_meet_time',  'ເວລາ (Meeting time)',                                 'TEXT'],
    ['divorce_meet_date',  'ຂອງວັນທີ (Meeting date)',                             'DATE'],
    ['divorce_med_village','ຄະນະໜ່ວຍໄກ່ເກັ່ຍຂໍ້ຂັດແຍ່ງຂັ້ນບ້ານ (Mediation village)', 'TEXT'],
    ['divorce_think_months','ໃຫ້ເວລາຄົ້ນຄິດ ເປັນເວລາ (Think months)',        'TEXT'],
    ['divorce_children',   'ມີລູກຮ່ວມກັນ (Children count)',                     'TEXT'],
    ['divorce_child1',     'ລູກ 1 (ທ້າວ/ນາງ + ອາຍຸ + ອາຊີບ)',                'TEXT'],
    ['divorce_child2',     'ລູກ 2 (ທ້າວ/ນາງ + ອາຍຸ + ອາຊີບ)',                'TEXT'],
    ['divorce_child3',     'ລູກ 3 (ທ້າວ/ນາງ + ອາຍຸ + ອາຊີບ)',                'TEXT'],
    ['divorce_custody',    'ໃຫ້ລູກ...ຄົນຢູ່ນຳ (Custody count + parent)',        'TEXT'],
    ['divorce_support',    'ຮັບຜິດຊອບລ້ຽງດູລູກ (Support count + terms)',     'TEXT'],
    ['divorce_original',   'ຊັບເດີມ (Original property)',                          'TEXTAREA'],
    ['divorce_joint',      'ສິນສົມສ້າງ (Joint property)',                         'TEXTAREA'],
    ['divorce_debt_pay',   'ໜີ້ຕ້ອງຮັບ (Debts to receive)',                        'TEXTAREA'],
    ['divorce_debt_send',  'ໜີ້ຕ້ອງສົ່ງ (Debts to pay)',                          'TEXTAREA'],
];

$inserted = 0; $sort = 1;
foreach ($fields as [$name, $label, $type]) {
    $st = $conn->prepare("SELECT id FROM edoc_doc_field WHERE doc_type_id=5 AND doc_item_id=58 AND field_name=?");
    $st->execute([$name]);
    if (!$st->fetchColumn()) {
        $conn->prepare("INSERT INTO edoc_doc_field (doc_type_id, doc_item_id, field_name, field_label, field_type, is_active, sort_order)
                        VALUES (5, 58, ?, ?, ?, 1, ?)")->execute([$name, $label, $type, $sort]);
        $inserted++;
    }
    $sort++;
}

try { $conn->commit(); } catch (Throwable $e) { /* ALTER TABLE auto-commits */ }
echo "divorce-record fields (item 58): $inserted inserted (" . count($fields) . " defined, idempotent)\n";
echo "DONE\n";