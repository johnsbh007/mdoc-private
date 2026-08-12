-- =====================================================================
-- Add columns to edoc_fee_type for Article 17 reference
-- Then update with 6 income categories from the law
-- =====================================================================

SET NAMES utf8mb4;

-- Add new columns
ALTER TABLE `edoc_fee_type`
  ADD COLUMN `name_en` VARCHAR(255) DEFAULT NULL AFTER `name_lo`,
  ADD COLUMN `article_ref` VARCHAR(10) DEFAULT NULL AFTER `name_en`,
  ADD COLUMN `clause_no` TINYINT UNSIGNED DEFAULT NULL AFTER `article_ref`,
  ADD COLUMN `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `is_active`;

-- Clear and re-seed with 6 income categories
TRUNCATE TABLE `edoc_fee_type`;

INSERT INTO `edoc_fee_type` (`fee_type`, `name_lo`, `name_en`, `article_ref`, `clause_no`, `is_active`, `sort_order`) VALUES
('LAND_TAX',     'ຄ່າທຳນຽມນຳໃຊ້ທີ່ດິນ (ອາກອນທີ່ດິນ)',                    'Land use fees (land tax)',                                  '17', 1, 1, 1),
('SERVICE_FEE',  'ລາຍຮັບຈາກຄ່າທຳນຽມ ແລະ ຄ່າບໍລິການວິຊາການ',              'Revenue from fees and technical service charges',            '17', 2, 1, 2),
('CONTRIBUTION', 'ການປະກອບສ່ວນ ຂອງສັງຄົມ',                                    'Social contributions',                                       '17', 3, 1, 3),
('RENTAL',       'ຄ່າເຊົ່າເຮືອນ, ທີ່ດິນ ແລະ ຊັບສິນ ຂອງລັດ ທີ່ຕາແສງຄຸ້ມຄອງ', 'Rent for houses, land, state assets managed by Taseng',     '17', 4, 1, 4),
('FINE',         'ລາຍຮັບຈາກການປັບໃໝ',                                          'Revenue from fines',                                       '17', 5, 1, 5),
('OTHER_INCOME', 'ລາຍຮັບອື່ນໆ ຕາມການມອບໝາຍ ຂອງນະຄອນ',                     'Other revenue as assigned by municipality',                 '17', 6, 1, 6);

-- Verify
SELECT `id`, `fee_type`, `name_lo`, `name_en`, `article_ref`, `clause_no`, `is_active`, `sort_order`
FROM `edoc_fee_type` ORDER BY `sort_order`;