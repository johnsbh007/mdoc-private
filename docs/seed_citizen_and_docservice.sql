-- Seed test citizens + a few doc_field + document signers for Document Service demo
-- Run against edoc_tasaeng on apis.com.la
SET NAMES utf8mb4;

-- ── Test citizens (village_id 1 = first village; residence hierarchy from edoc_village) ──
INSERT INTO edoc_citizen (phone, email, password_hash, full_name, first_name, last_name, id_card, village_id, address_line, dob, gender, is_active)
VALUES
  ('02051234567', 'somchai@example.com',  '$2y$10$abcdefghijklmnopqrstuv', 'ສົມໃຈ ວົງວິໄລ', 'ສົມໃຈ', 'ວົງວິໄລ',  '010101010101', 1, 'ບ້ານນາຕ້ອງ ເລກທີ່ 12', '1985-04-12', 'M', 1),
  ('02059876543', 'nounoi@example.com',   '$2y$10$abcdefghijklmnopqrstuv', 'ນາງນ້ອຍ ສິດທິພອນ', 'ນ້ອຍ',  'ສິດທິພອນ',  '020202020202', 2, 'ບ້ານໂພນສະຫວັນ ເລກທີ່ 45', '1992-09-30', 'F', 1),
  ('02057778899', NULL,                   '$2y$10$abcdefghijklmnopqrstuv', 'ທ້າວໄຊ ພົມມະວັນ',  'ໄຊ',   'ພົມມະວັນ',  '030303030303', 3, 'ບ້ານຫ້ວຍຊາຍ ເລກທີ່ 7',  '1978-01-05', 'M', 1);

-- ── Document fields (configurable inputs per doc_type) ──
-- For doc_type 01 (certification) and 04 (contract)
INSERT INTO edoc_doc_field (doc_type_id, field_name, field_label, field_type, is_active, sort_order) VALUES
  (1, 'subject_name',   'ຊື່ຜູ້ຮັບໜັງສື',     'TEXT',     1, 1),
  (1, 'purpose',        'ຈຸດປະສົງ',           'TEXTAREA', 1, 2),
  (1, 'related_place',  'ສະຖານທີ່ກ່ຽວຂ້ອງ',   'TEXT',     1, 3),
  (4, 'party_a',        'ຄູ່ສັນຍາ ຝ່າຍ ກ',  'TEXT',     1, 1),
  (4, 'party_b',        'ຄູ່ສັນຍາ ຝ່າຍ ຂ',  'TEXT',     1, 2),
  (4, 'contract_value', 'ມູນຄ່າສັນຍາ (ກີບ)', 'NUMBER',   1, 3);

-- ── Document signers (per doc_type; officer signs) ──
INSERT INTO edoc_document_signer (doc_type_id, village_id, off_id, signer_order, signer_name, signer_title, is_active) VALUES
  (1, NULL, '00', 1, 'ຫົວໜ້າຕາແສງ', 'ຫົວໜ້າຕາແສງ', 1),
  (4, NULL, '00', 1, 'ຫົວໜ້າຕາແສງ', 'ຫົວໜ້າຕາແສງ', 1),
  (4, NULL, '00', 2, 'ຜູ້ອຳນວຍການ', 'ຜູ້ອຳນວຍການ',   1);
