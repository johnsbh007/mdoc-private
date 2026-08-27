<?php
/**
 * Migration: ໃບຢັ້ງຢືນສາຍຍາດ (kinship certificate, doc_type 1, doc_item 5)
 * 1. Add edoc_doc_field.doc_item_id (nullable). NULL = generic for the whole doc_type
 *    (used by type-14 land-sale fields); set = item-specific (used by residence item 1
 *    and this new kinship item 5). Existing type-1 fields become item-specific to item 1.
 * 2. Insert the kinship field set for item 5 (applicant extras, father/mother/siblings,
 *    spouse + spouse's family, children, other relatives).
 * Idempotent.
 */
require __DIR__ . '/../connect.php';

$conn->beginTransaction();

// 1. doc_item_id column
$hasCol = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='edoc_doc_field' AND COLUMN_NAME='doc_item_id'")->fetchColumn();
if (!$hasCol) {
    $conn->exec("ALTER TABLE edoc_doc_field ADD COLUMN doc_item_id BIGINT UNSIGNED NULL AFTER doc_type_id,
                 ADD KEY idx_field_item (doc_item_id)");
    echo "edoc_doc_field.doc_item_id added\n";
} else {
    echo "edoc_doc_field.doc_item_id already exists\n";
}

// existing type-1 fields belong to the residence item (item 1) — not generic anymore
$conn->prepare("UPDATE edoc_doc_field SET doc_item_id=1 WHERE doc_type_id=1 AND doc_item_id IS NULL")->execute();

// 2. kinship fields for item 5 (unique by field_name)
$fields = [
    ['kin_issued_by',      'ອອກໃຫ້ໂດຍ (ID issued by)',                       'TEXT'],
    // father
    ['father_name',        'ຊື່ ພໍ່ (Father name)',                            'TEXT'],
    ['father_age',         'ອາຍຸ ພໍ່ (Father age)',                           'TEXT'],
    ['father_occupation',  'ອາຊີບ ພໍ່ (Father occupation)',                   'TEXT'],
    ['father_village',     'ບ້ານ ພໍ່ (Father village)',                       'TEXT'],
    // mother
    ['mother_name',        'ຊື່ ແມ່ (Mother name)',                           'TEXT'],
    ['mother_age',         'ອາຍຸ ແມ່ (Mother age)',                           'TEXT'],
    ['mother_occupation',  'ອາຊີບ ແມ່ (Mother occupation)',                   'TEXT'],
    ['mother_village',     'ບ້ານ ແມ່ (Mother village)',                       'TEXT'],
    // siblings 1-3 (name/age/occupation/village)
    ['sib1_name','ອ້າຍ/ເອື້ອຍ/ນ້ອງ 1 ຊື່','TEXT'], ['sib1_age','ອ້າຍ/ເອື້ອຍ/ນ້ອງ 1 ອາຍຸ','TEXT'],
    ['sib1_occupation','ອ້າຍ/ເອື້ອຍ/ນ້ອງ 1 ອາຊີບ','TEXT'], ['sib1_village','ອ້າຍ/ເອື້ອຍ/ນ້ອງ 1 ບ້ານ','TEXT'],
    ['sib2_name','ອ້າຍ/ເອື້ອຍ/ນ້ອງ 2 ຊື່','TEXT'], ['sib2_age','ອ້າຍ/ເອື້ອຍ/ນ້ອງ 2 ອາຍຸ','TEXT'],
    ['sib2_occupation','ອ້າຍ/ເອື້ອຍ/ນ້ອງ 2 ອາຊີບ','TEXT'], ['sib2_village','ອ້າຍ/ເອື້ອຍ/ນ້ອງ 2 ບ້ານ','TEXT'],
    ['sib3_name','ອ້າຍ/ເອື້ອຍ/ນ້ອງ 3 ຊື່','TEXT'], ['sib3_age','ອ້າຍ/ເອື້ອຍ/ນ້ອງ 3 ອາຍຸ','TEXT'],
    ['sib3_occupation','ອ້າຍ/ເອື້ອຍ/ນ້ອງ 3 ອາຊີບ','TEXT'], ['sib3_village','ອ້າຍ/ເອື້ອຍ/ນ້ອງ 3 ບ້ານ','TEXT'],
    // spouse
    ['spouse_name',        'ຊື່ ຜົວ/ເມຍ (Spouse name)',                       'TEXT'],
    ['spouse_dob',         'ວັນ,ເດືອນ,ປີເກີດ ຜົວ/ເມຍ (Spouse DOB)',       'DATE'],
    ['spouse_nationality', 'ສັນຊາດ ຜົວ/ເມຍ (Spouse nationality)',            'TEXT'],
    ['spouse_occupation',  'ອາຊີບ ຜົວ/ເມຍ (Spouse occupation)',             'TEXT'],
    ['spouse_unit',        'ໜ່ວຍ ຜົວ/ເມຍ (Spouse unit)',                     'TEXT'],
    ['spouse_village',     'ບ້ານ ຜົວ/ເມຍ (Spouse village)',                  'TEXT'],
    ['spouse_district',    'ເມືອງ ຜົວ/ເມຍ (Spouse district)',                'TEXT'],
    ['spouse_province',    'ແຂວງ ຜົວ/ເມຍ (Spouse province)',                 'TEXT'],
    // spouse's father/mother
    ['sp_father_name','ຊື່ພໍ່ ຜົວ/ເມຍ (Spouse father name)','TEXT'], ['sp_father_age','ອາຍຸ ພໍ່ ຜົວ/ເມຍ','TEXT'],
    ['sp_father_occupation','ອາຊີບ ພໍ່ ຜົວ/ເມຍ','TEXT'], ['sp_father_village','ບ້ານ ພໍ່ ຜົວ/ເມຍ','TEXT'],
    ['sp_mother_name','ຊື່ແມ່ ຜົວ/ເມຍ (Spouse mother name)','TEXT'], ['sp_mother_age','ອາຍຸ ແມ່ ຜົວ/ເມຍ','TEXT'],
    ['sp_mother_occupation','ອາຊີບ ແມ່ ຜົວ/ເມຍ','TEXT'], ['sp_mother_village','ບ້ານ ແມ່ ຜົວ/ເມຍ','TEXT'],
    // spouse's siblings/relatives 1-3
    ['spr1_name','ຍາດ ຜົວ/ເມຍ 1 ຊື່','TEXT'], ['spr1_age','ຍາດ ຜົວ/ເມຍ 1 ອາຍຸ','TEXT'],
    ['spr1_occupation','ຍາດ ຜົວ/ເມຍ 1 ອາຊີບ','TEXT'], ['spr1_village','ຍາດ ຜົວ/ເມຍ 1 ບ້ານ','TEXT'],
    ['spr2_name','ຍາດ ຜົວ/ເມຍ 2 ຊື່','TEXT'], ['spr2_age','ຍາດ ຜົວ/ເມຍ 2 ອາຍຸ','TEXT'],
    ['spr2_occupation','ຍາດ ຜົວ/ເມຍ 2 ອາຊີບ','TEXT'], ['spr2_village','ຍາດ ຜົວ/ເມຍ 2 ບ້ານ','TEXT'],
    ['spr3_name','ຍາດ ຜົວ/ເມຍ 3 ຊື່','TEXT'], ['spr3_age','ຍາດ ຜົວ/ເມຍ 3 ອາຍຸ','TEXT'],
    ['spr3_occupation','ຍາດ ຜົວ/ເມຍ 3 ອາຊີບ','TEXT'], ['spr3_village','ຍາດ ຜົວ/ເມຍ 3 ບ້ານ','TEXT'],
    // children 1-3
    ['child1_name','ລູກ 1 ຊື່','TEXT'], ['child1_age','ລູກ 1 ອາຍຸ','TEXT'],
    ['child1_occupation','ລູກ 1 ອາຊີບ','TEXT'], ['child1_village','ລູກ 1 ບ້ານ','TEXT'],
    ['child2_name','ລູກ 2 ຊື່','TEXT'], ['child2_age','ລູກ 2 ອາຍຸ','TEXT'],
    ['child2_occupation','ລູກ 2 ອາຊີບ','TEXT'], ['child2_village','ລູກ 2 ບ້ານ','TEXT'],
    ['child3_name','ລູກ 3 ຊື່','TEXT'], ['child3_age','ລູກ 3 ອາຍຸ','TEXT'],
    ['child3_occupation','ລູກ 3 ອາຊີບ','TEXT'], ['child3_village','ລູກ 3 ບ້ານ','TEXT'],
    // other relatives 1-3
    ['rel1_name','ຍາດອື່ນ 1 ຊື່','TEXT'], ['rel1_age','ຍາດອື່ນ 1 ອາຍຸ','TEXT'],
    ['rel1_occupation','ຍາດອື່ນ 1 ອາຊີບ','TEXT'], ['rel1_village','ຍາດອື່ນ 1 ບ້ານ','TEXT'],
    ['rel2_name','ຍາດອື່ນ 2 ຊື່','TEXT'], ['rel2_age','ຍາດອື່ນ 2 ອາຍຸ','TEXT'],
    ['rel2_occupation','ຍາດອື່ນ 2 ອາຊີບ','TEXT'], ['rel2_village','ຍາດອື່ນ 2 ບ້ານ','TEXT'],
    ['rel3_name','ຍາດອື່ນ 3 ຊື່','TEXT'], ['rel3_age','ຍາດອື່ນ 3 ອາຍຸ','TEXT'],
    ['rel3_occupation','ຍາດອື່ນ 3 ອາຊີບ','TEXT'], ['rel3_village','ຍາດອື່ນ 3 ບ້ານ','TEXT'],
];

$inserted = 0; $sort = 1;
foreach ($fields as [$name, $label, $type]) {
    $st = $conn->prepare("SELECT id FROM edoc_doc_field WHERE doc_type_id=1 AND doc_item_id=5 AND field_name=?");
    $st->execute([$name]);
    if (!$st->fetchColumn()) {
        $conn->prepare("INSERT INTO edoc_doc_field (doc_type_id, doc_item_id, field_name, field_label, field_type, is_active, sort_order)
                        VALUES (1, 5, ?, ?, ?, 1, ?)")->execute([$name, $label, $type, $sort]);
        $inserted++;
    }
    $sort++;
}
$conn->commit();
echo "kinship fields (item 5): $inserted inserted (" . count($fields) . " defined, idempotent)\n";
echo "DONE\n";