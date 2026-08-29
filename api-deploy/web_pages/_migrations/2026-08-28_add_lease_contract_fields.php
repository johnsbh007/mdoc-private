<?php
/**
 * Migration: ໃບສັນຍາເຊົ່າທີ່ດິນ ແລະ ເຮືອນ (Land & House Lease Contract, doc_type 6, doc_item 30)
 * Creates item-30-specific EAV fields. Owner = applicant citizen; Renter = buyer citizen
 * (buyer_id). Land location/boundaries/payment terms are EAV. Direction table (P16-19)
 * stays blank for handwriting (no dots to fill).
 * Idempotent.
 */
require __DIR__ . '/../connect.php';

$conn->beginTransaction();

$fields = [
    ['lease_owner_spouse',   'ຊື່ຜົວ ຫຼື ເມຍ ຂອງ ເຈົ້າຂອງຊັບ (Owner spouse name)',       'TEXT'],
    ['lease_renter_spouse',  'ຊື່ຜົວ ຫຼື ເມຍ ຂອງ ຜູ້ເຂົ້າເຊົ່າ (Renter spouse name)',  'TEXT'],
    ['lease_property_desc',  'ຊັບທີ່ໃຫ້ເຊົ່າ (Leased property description)',              'TEXT'],
    ['land_village',         'ບ້ານທີ່ຕັ້ງຊັບ (Property village)',                        'TEXT'],
    ['land_district',        'ເມືອງ ທີ່ຕັ້ງຊັບ (Property district)',                     'TEXT'],
    ['land_province',        'ແຂວງ ທີ່ຕັ້ງຊັບ (Property province)',                      'TEXT'],
    ['lease_area_m2',        'ລວມເນື້ອທີ່ (Total area m²)',                              'TEXT'],
    ['lease_term',           'ກໍານົດເຊົ່າ (Lease term)',                                  'TEXT'],
    ['lease_start_date',     'ເລີ່ມແຕ່ວັນທີ (Lease start date)',                         'DATE'],
    ['lease_end_date',       'ຈົນເຖິງວັນທີ (Lease end date)',                            'DATE'],
    ['lease_price_year',     'ໃນລາຄາ ຕໍ່ປີ (Rent per year)',                              'TEXT'],
    ['lease_deposit',        'ຈ່າຍມັດຈໍາລ່ວງໜ້າ (Deposit)',                             'TEXT'],
    ['lease_installment',    'ຈ່າຍງວດປົກກະຕິ (Regular installment)',                   'TEXT'],
];

$inserted = 0; $sort = 1;
foreach ($fields as [$name, $label, $type]) {
    $st = $conn->prepare("SELECT id FROM edoc_doc_field WHERE doc_type_id=6 AND doc_item_id=30 AND field_name=?");
    $st->execute([$name]);
    if (!$st->fetchColumn()) {
        $conn->prepare("INSERT INTO edoc_doc_field (doc_type_id, doc_item_id, field_name, field_label, field_type, is_active, sort_order)
                        VALUES (6, 30, ?, ?, ?, 1, ?)")->execute([$name, $label, $type, $sort]);
        $inserted++;
    }
    $sort++;
}

try { $conn->commit(); } catch (Throwable $e) { /* ALTER TABLE auto-commits */ }
echo "lease contract fields (item 30): $inserted inserted (" . count($fields) . " defined, idempotent)\n";
echo "DONE\n";