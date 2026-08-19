-- ============================================================
-- Replace Taseng & Village data for district 129
--   1601  = Nakhone Pakse (ນະຄອນປາກເຊ), Champasack province (16)
-- Source: docs/source/ໂຄງຮ່າງການຈັດຕັ້ງ ອົງການປົກຄອງທ້ອງຖີ່ນ.pdf
-- ============================================================
-- STEP 0: backup affected rows (dist 129) BEFORE any change
-- ============================================================
CREATE TABLE IF NOT EXISTS bak_edoc_taseng_dist129 LIKE edoc_taseng;
INSERT INTO bak_edoc_taseng_dist129 SELECT * FROM edoc_taseng WHERE district_id=129;
CREATE TABLE IF NOT EXISTS bak_edoc_village_dist129 LIKE edoc_village;
INSERT INTO bak_edoc_village_dist129 SELECT * FROM edoc_village WHERE tasseng_id IN (SELECT id FROM edoc_taseng WHERE district_id=129);

-- ============================================================
-- STEP 1: delete existing (wrong) tasengs + their villages for dist 129
-- ============================================================
DELETE FROM edoc_village WHERE tasseng_id IN (SELECT id FROM edoc_taseng WHERE district_id=129);
DELETE FROM edoc_taseng WHERE district_id=129;

-- ============================================================
-- STEP 2: insert the 4 correct tasengs
--    tas_code 01 => ncode 160101 ; off_id 160101
-- ============================================================
INSERT INTO edoc_taseng (district_id, tas_code, ncode, name_lo, name_en, off_id) VALUES
(129, '01', '160101', 'ປາກເຊ',     'Pakse',        '160101'),
(129, '02', '160102', 'ສະໜາມໄຊ',   'Sanamxay',     '160102'),
(129, '03', '160103', 'ໂຊກອຳນວຍ',  'Xokamnuay',    '160103'),
(129, '04', '160104', 'ຄັນເກິງ',    'Khangkhong',   '160104');

SET @t1 = (SELECT id FROM edoc_taseng WHERE district_id=129 AND tas_code='01');
SET @t2 = (SELECT id FROM edoc_taseng WHERE district_id=129 AND tas_code='02');
SET @t3 = (SELECT id FROM edoc_taseng WHERE district_id=129 AND tas_code='03');
SET @t4 = (SELECT id FROM edoc_taseng WHERE district_id=129 AND tas_code='04');

-- ============================================================
-- STEP 3: insert villages
--   code = 1601 + taseng(2) + village seq(2)   e.g. 16010101
--   off_id = code (office = village)
-- ============================================================
-- Taseng 01 ປາກເຊ (10 villages)
INSERT INTO edoc_village (tasseng_id, vil_code, ncode, name_lo, name_en, off_id) VALUES
(@t1, '16010101', '16010101', 'ບ້ານປາກເຊ',       'Pakse',       '16010101'),
(@t1, '16010102', '16010102', 'ບ້ານພະບາດ',       'Phabath',     '16010102'),
(@t1, '16010103', '16010103', 'ບ້ານໂພນສະຫວັນ',   'Phonsavan',   '16010103'),
(@t1, '16010104', '16010104', 'ບ້ານຫຼັກເມືອງ',    'Lakmuang',    '16010104'),
(@t1, '16010105', '16010105', 'ບ້ານຮ່ອງຂະຍອມ',    'Hongkhayom',  '16010105'),
(@t1, '16010106', '16010106', 'ບ້ານທົ່ງ',         'Thong',       '16010106'),
(@t1, '16010107', '16010107', 'ບ້ານໂພນໄຊ',       'Phonxai',     '16010107'),
(@t1, '16010108', '16010108', 'ບ້ານສາງນໍ້າມັນ',   'Sangnaman',   '16010108'),
(@t1, '16010109', '16010109', 'ບ້ານໂພນກຸງ',      'Phonkung',    '16010109'),
(@t1, '16010110', '16010110', 'ບ້ານທ່າຫຼວງ',      'Tha Luang',   '16010110');

-- Taseng 02 ສະໜາມໄຊ (8 villages)
INSERT INTO edoc_village (tasseng_id, vil_code, ncode, name_lo, name_en, off_id) VALUES
(@t2, '16010201', '16010201', 'ບ້ານສະໜາມໄຊ',     'Sanamxay',     '16010201'),
(@t2, '16010202', '16010202', 'ບ້ານໂນນສະຫວ່າງ',   'Non Savang',   '16010202'),
(@t2, '16010203', '16010203', 'ບ້ານສວນສະຫວັນ',   'Suan Savan',   '16010203'),
(@t2, '16010204', '16010204', 'ບ້ານຈັດສັນ',       'Xatsan',       '16010204'),
(@t2, '16010205', '16010205', 'ບ້ານຄອນໄຫຼ',       'Khonlai',      '16010205'),
(@t2, '16010206', '16010206', 'ບ້ານພູມ່ວງ',       'Phoumouang',   '16010206'),
(@t2, '16010207', '16010207', 'ບ້ານກາງ',          'Kang',         '16010207'),
(@t2, '16010208', '16010208', 'ບ້ານພັດທະນາ',      'Phatthana',    '16010208');

-- Taseng 03 ໂຊກອຳນວຍ (11 villages)
INSERT INTO edoc_village (tasseng_id, vil_code, ncode, name_lo, name_en, off_id) VALUES
(@t3, '16010301', '16010301', 'ບ້ານໂຊກອຳນວຍ',   'Xok Amnuay',   '16010301'),
(@t3, '16010302', '16010302', 'ບ້ານຫ້ວຍຍາງຄຳ',  'Houay Yangkham','16010302'),
(@t3, '16010303', '16010303', 'ບ້ານແກ',         'Ke',           '16010303'),
(@t3, '16010304', '16010304', 'ບ້ານແຫ່',        'Hae',          '16010304'),
(@t3, '16010305', '16010305', 'ບ້ານໂພນສະອາດ',  'Phon Xxat',    '16010305'),
(@t3, '16010306', '16010306', 'ບ້ານຊົ້ງ',        'Song',         '16010306'),
(@t3, '16010307', '16010307', 'ບ້ານນາຈຽງ',      'Na Xieng',     '16010307'),
(@t3, '16010308', '16010308', 'ບ້ານໂພຕາກ',      'Pho Tak',      '16010308'),
(@t3, '16010309', '16010309', 'ບ້ານແກ້ວສຳພັນ', 'Keo Samphan',  '16010309'),
(@t3, '16010310', '16010310', 'ບ້ານໂນນດູ່',     'Non Du',       '16010310'),
(@t3, '16010311', '16010311', 'ບ້ານນາແຫກ',      'Na Hek',       '16010311');

-- Taseng 04 ຄັນເກິງ (13 villages)
INSERT INTO edoc_village (tasseng_id, vil_code, ncode, name_lo, name_en, off_id) VALUES
(@t4, '16010401', '16010401', 'ບ້ານຄັນເກິງ',    'Kankeng',       '16010401'),
(@t4, '16010402', '16010402', 'ບ້ານແກ້ງເກິງ',   'Kaeng Keng',    '16010402'),
(@t4, '16010403', '16010403', 'ບ້ານສະພານໄຊ',   'Saphanxay',     '16010403'),
(@t4, '16010404', '16010404', 'ບ້ານທ່າໄຮ',      'Tha Hai',       '16010404'),
(@t4, '16010405', '16010405', 'ບ້ານທ່າຫີນ',     'Tha Hin',       '16010405'),
(@t4, '16010406', '16010406', 'ບ້ານຫ້ວຍປູນ',    'Houay Pun',     '16010406'),
(@t4, '16010407', '16010407', 'ບ້ານກົກເດື່ອ',   'Kok Duea',      '16010407'),
(@t4, '16010408', '16010408', 'ບ້ານໂພນງາມ',     'Phon Ngam',     '16010408'),
(@t4, '16010409', '16010409', 'ບ້ານດອນເຂາະ',    'Don Kho',       '16010409'),
(@t4, '16010410', '16010410', 'ບ້ານດົງກະໂລງ',   'Dong Kalong',   '16010410'),
(@t4, '16010411', '16010411', 'ບ້ານໂພນສີໄຄ',    'Phon Si Khai',  '16010411'),
(@t4, '16010412', '16010412', 'ບ້ານແກ້ງຊັນ',    'Kaeng Xan',     '16010412'),
(@t4, '16010413', '16010413', 'ບ້ານຫວຍເລົາ',    'Houay Lao',     '16010413');
