<?php
/**
 * Migration: ຊີວະປະຫວັດທົ່ວໄປ (Biography / CV form, doc_type 1, doc_item 8)
 * Creates item-8-specific fields for the multi-page biography form. The applicant's
 * own profile (name/dob/geo/id card/phone) comes from the citizen record; the rest
 * (family, spouse, work history, education, languages, self-assessment) are EAV.
 * Internal tables (work history / training / awards / discipline / children) stay
 * blank for handwriting — only paragraph-dot fields are auto-filled.
 * Idempotent.
 */
require __DIR__ . '/../connect.php';

$conn->beginTransaction();

$fields = [
    // Section I — self (beyond the citizen record)
    ['bio_class',            'ຊົນຊັ້ນ (Social class)',                              'TEXT'],
    ['bio_house_no',         'ບ່ອນຢູ່ປະຈຸບັນ ເລກບ້ານ (Current house no)',        'TEXT'],
    ['bio_family_book_no',   'ສຳມະໂນຄົວເລກທີ່ (Family book no)',                 'TEXT'],
    ['bio_family_book_date', 'ລົງວັນທີ ສຳມະໂນຄົວ (Family book date)',           'DATE'],
    ['bio_family_book_at',   'ສຳມະໂນຄົວອອກໃຫ້ທີ່ (Family book issued at)',       'TEXT'],
    ['bio_id_issue_at',      'ບັດປະຈຳຕົວອອກໃຫ້ທີ່ (ID issued at)',             'TEXT'],
    ['bio_workplace',        'ບ່ອນປະຈຳການປະຈຸບັນ (Current workplace)',          'TEXT'],
    ['bio_rev_date',         'ວັນ,ເດືອນ,ປີ ເຂົ້າການປະຕິວັດ (Revolution entry)', 'DATE'],
    ['bio_party_date',       'ວັນ,ເດືອນ,ປີ ເຂົ້າສັງກັດລັດ (Party org entry)',   'DATE'],
    ['bio_position',         'ໜ້າທີ່ຮັບຜິດຊອບ (Current position)',               'TEXT'],
    ['bio_salary',           'ຊັ້ນ,ຂັ້ນເງິນເດືອນ (Salary grade)',                'TEXT'],
    ['bio_youth_date',       'ເຂົ້າອົງການຊາວໜຸ່ມ ວັນທີ (Youth org date)',       'DATE'],
    ['bio_youth_at',         'ອົງການຊາວໜຸ່ມ ຢູ່ໃສ (Youth org place)',            'TEXT'],
    ['bio_women_date',       'ເຂົ້າສະຫະພັນແມ່ຍິງ ວັນທີ (Women union date)',   'DATE'],
    ['bio_women_at',         'ສະຫະພັນແມ່ຍິງ ຢູ່ໃສ (Women union place)',        'TEXT'],
    ['bio_union_date',       'ເຂົ້າກຳມະບານ ວັນທີ (Trade union date)',            'DATE'],
    ['bio_union_at',         'ກຳມະບານ ຢູ່ໃສ (Trade union place)',                  'TEXT'],
    ['bio_party_reserve',    'ເຂົ້າພັກສຳຮອງ ວັນທີ (Party reserve date)',         'DATE'],
    ['bio_party_full',       'ເຂົ້າພັກສົມບູນ ວັນທີ (Full party date)',          'DATE'],
    ['bio_recruiter',        'ຜູ້ຮັບເຂົ້າພັກ ຊື່ (Party recruiter name)',        'TEXT'],
    ['bio_recruiter_now',    'ຜູ້ຮັບເຂົ້າພັກ ດຽວນີ້ເຮັດຫຍັງ,ຢູ່ໃສ (Recruiter now)', 'TEXT'],
    ['bio_educ_primary',     'ຈົບປະຖົມ (Primary graduation)',                     'TEXT'],
    ['bio_educ_lower',       'ຈົບມໍຕົ້ນ (Lower secondary)',                        'TEXT'],
    ['bio_educ_upper',       'ຈົບມໍປາຍ (Upper secondary)',                          'TEXT'],
    ['bio_special',          'ລະດັບວິຊາສະເພາະ (Specialized training)',            'TEXTAREA'],
    ['bio_politics',         'ລະດັບທິດສະດີການເມືອງ (Political theory)',        'TEXTAREA'],
    ['bio_lang_en',          'ພາສາອັງກິດ (English level)',                         'TEXT'],
    ['bio_lang_fr',          'ພາສາຝລັ່ງ (French level)',                            'TEXT'],
    ['bio_lang_ru',          'ພາສາລັດເຊຍ (Russian level)',                          'TEXT'],
    ['bio_lang_other',       'ພາສາອື່ນໆ (Other languages)',                          'TEXT'],
    ['bio_policies',         'ນະໂຍບາຍຕ່າງໆທີ່ໄດ້ຮັບ (Policies received)',       'TEXTAREA'],
    // Section II — parents & siblings
    ['bio_father_name',      'ຊື່ ແລະ ນາມສະກຸນ ພໍ່ (Father name)',                'TEXT'],
    ['bio_father_dob',       'ວັນ,ເດືອນ,ປີເກີດ ພໍ່ (Father DOB)',                'DATE'],
    ['bio_father_nat',       'ສັນຊາດ ພໍ່ (Father nationality)',                    'TEXT'],
    ['bio_father_eth',       'ຊົນເຜົ່າ ພໍ່ (Father ethnicity)',                    'TEXT'],
    ['bio_father_rel',       'ສາສະໜາ ພໍ່ (Father religion)',                        'TEXT'],
    ['bio_father_village',   'ບ້ານຢູ່ປະຈຸບັນ ພໍ່ (Father village)',              'TEXT'],
    ['bio_father_district',  'ເມືອງ ພໍ່ (Father district)',                        'TEXT'],
    ['bio_father_province',  'ແຂວງ ພໍ່ (Father province)',                           'TEXT'],
    ['bio_father_job',       'ອາຊີບປະຈຸບັນ ພໍ່ (Father occupation)',             'TEXT'],
    ['bio_father_pre1975',   'ກ່ອນປີ 1975 ພໍ່ເຮັດຫຍັງ (Father pre-1975)',       'TEXTAREA'],
    ['bio_father_sibs',      'ອ້າຍເອື້ອຍນ້ອງຂອງພໍ່ (Father siblings)',         'TEXTAREA'],
    ['bio_mother_name',      'ຊື່ ແລະ ນາມສະກຸນ ແມ່ (Mother name)',               'TEXT'],
    ['bio_mother_dob',       'ວັນ,ເດືອນ,ປີເກີດ ແມ່ (Mother DOB)',                'DATE'],
    ['bio_mother_nat',       'ສັນຊາດ ແມ່ (Mother nationality)',                    'TEXT'],
    ['bio_mother_eth',       'ຊົນເຜົ່າ ແມ່ (Mother ethnicity)',                    'TEXT'],
    ['bio_mother_rel',       'ສາສະໜາ ແມ່ (Mother religion)',                        'TEXT'],
    ['bio_mother_village',   'ບ້ານຢູ່ປະຈຸບັນ ແມ່ (Mother village)',             'TEXT'],
    ['bio_mother_district',  'ເມືອງ ແມ່ (Mother district)',                        'TEXT'],
    ['bio_mother_province',  'ແຂວງ ແມ່ (Mother province)',                          'TEXT'],
    ['bio_mother_job',       'ອາຊີບປະຈຸບັນ ແມ່ (Mother occupation)',            'TEXT'],
    ['bio_mother_pre1975',   'ກ່ອນປີ 1975 ແມ່ເຮັດຫຍັງ (Mother pre-1975)',      'TEXTAREA'],
    ['bio_mother_sibs',      'ອ້າຍເອື້ອຍນ້ອງຂອງແມ່ (Mother siblings)',         'TEXTAREA'],
    ['bio_own_sibs',         'ອ້າຍເອື້ອຍນ້ອງຂອງຕົນ (Own siblings)',            'TEXTAREA'],
    // Section III — spouse & spouse family
    ['bio_spouse_name',      'ຊື່ ແລະ ນາມສະກຸນ ຜົວ/ເມຍ (Spouse name)',           'TEXT'],
    ['bio_spouse_dob',       'ວັນ,ເດືອນ,ປີເກີດ ຜົວ/ເມຍ (Spouse DOB)',           'DATE'],
    ['bio_spouse_nat',       'ສັນຊາດ ຜົວ/ເມຍ (Spouse nationality)',               'TEXT'],
    ['bio_spouse_eth',       'ຊົນເຜົ່າ ຜົວ/ເມຍ (Spouse ethnicity)',               'TEXT'],
    ['bio_spouse_rel',       'ສາສະໜາ ຜົວ/ເມຍ (Spouse religion)',                   'TEXT'],
    ['bio_spouse_job',       'ອາຊີບປະຈຸບັນ ຜົວ/ເມຍ (Spouse occupation)',       'TEXT'],
    ['bio_spouse_workplace', 'ບ່ອນປະຈຳການ ຜົວ/ເມຍ (Spouse workplace)',          'TEXT'],
    ['bio_children_count',   'ມີລູກນຳກັນຈັກຄົນ (Number of children)',             'TEXT'],
    ['bio_sp_father_name',   'ຊື່ ພໍ່ຂອງ ຜົວ/ເມຍ (Spouse father name)',           'TEXT'],
    ['bio_sp_father_dob',    'ວັນ,ເດືອນ,ປີເກີດ ພໍ່ຂອງຜົວ/ເມຍ',               'DATE'],
    ['bio_sp_father_nat',    'ສັນຊາດ ພໍ່ຂອງຜົວ/ເມຍ',                             'TEXT'],
    ['bio_sp_father_eth',    'ຊົນເຜົ່າ ພໍ່ຂອງຜົວ/ເມຍ',                           'TEXT'],
    ['bio_sp_father_rel',    'ສາສະໜາ ພໍ່ຂອງຜົວ/ເມຍ',                             'TEXT'],
    ['bio_sp_father_job',    'ອາຊີບ ພໍ່ຂອງຜົວ/ເມຍ',                              'TEXT'],
    ['bio_sp_father_at',     'ບ່ອນປະຈຳການ ພໍ່ຂອງຜົວ/ເມຍ',                      'TEXT'],
    ['bio_sp_father_village','ບ້ານຢູ່ປະຈຸບັນ ພໍ່ຂອງຜົວ/ເມຍ',                   'TEXT'],
    ['bio_sp_father_district','ເມືອງ ພໍ່ຂອງຜົວ/ເມຍ',                            'TEXT'],
    ['bio_sp_father_province','ແຂວງ ພໍ່ຂອງຜົວ/ເມຍ',                             'TEXT'],
    ['bio_sp_mother_name',   'ຊື່ ແມ່ຂອງ ຜົວ/ເມຍ (Spouse mother name)',          'TEXT'],
    ['bio_sp_mother_dob',    'ວັນ,ເດືອນ,ປີເກີດ ແມ່ຂອງຜົວ/ເມຍ',               'DATE'],
    ['bio_sp_mother_nat',    'ສັນຊາດ ແມ່ຂອງຜົວ/ເມຍ',                            'TEXT'],
    ['bio_sp_mother_eth',    'ຊົນເຜົ່າ ແມ່ຂອງຜົວ/ເມຍ',                          'TEXT'],
    ['bio_sp_mother_rel',    'ສາສະໜາ ແມ່ຂອງຜົວ/ເມຍ',                            'TEXT'],
    ['bio_sp_mother_job',    'ອາຊີບ ແມ່ຂອງຜົວ/ເມຍ',                             'TEXT'],
    ['bio_sp_mother_at',     'ບ່ອນປະຈຳການ ແມ່ຂອງຜົວ/ເມຍ',                     'TEXT'],
    ['bio_sp_mother_village','ບ້ານຢູ່ປະຈຸບັນ ແມ່ຂອງຜົວ/ເມຍ',                  'TEXT'],
    ['bio_sp_mother_district','ເມືອງ ແມ່ຂອງຜົວ/ເມຍ',                           'TEXT'],
    ['bio_sp_mother_province','ແຂວງ ແມ່ຂອງຜົວ/ເມຍ',                            'TEXT'],
    ['bio_sp_sibs',          'ອ້າຍເອື້ອຍນ້ອງຂອງຜົວ/ເມຍ (Spouse siblings)',    'TEXTAREA'],
    // Section IV — self-assessment
    ['bio_strengths',        'ຈຸດດີພື້ນຖານ (Strengths)',                          'TEXTAREA'],
    ['bio_weaknesses',       'ຈຸດອ່ອນພື້ນຖານ (Weaknesses)',                       'TEXTAREA'],
    ['bio_health',           'ສຸຂະພາບ (Health)',                                   'TEXT'],
];

$inserted = 0; $sort = 1;
foreach ($fields as [$name, $label, $type]) {
    $st = $conn->prepare("SELECT id FROM edoc_doc_field WHERE doc_type_id=1 AND doc_item_id=8 AND field_name=?");
    $st->execute([$name]);
    if (!$st->fetchColumn()) {
        $conn->prepare("INSERT INTO edoc_doc_field (doc_type_id, doc_item_id, field_name, field_label, field_type, is_active, sort_order)
                        VALUES (1, 8, ?, ?, ?, 1, ?)")->execute([$name, $label, $type, $sort]);
        $inserted++;
    }
    $sort++;
}

try { $conn->commit(); } catch (Throwable $e) { /* ALTER TABLE auto-commits */ }
echo "biography fields (item 8): $inserted inserted (" . count($fields) . " defined, idempotent)\n";
echo "DONE\n";