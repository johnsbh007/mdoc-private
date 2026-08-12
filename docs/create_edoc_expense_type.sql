-- =====================================================================
-- Create edoc_expense_type table for Article 18 (Expenditure categories)
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `edoc_expense_type` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `expense_type`    VARCHAR(50)     NOT NULL COMMENT 'Short code e.g., SALARY, ADMIN, INVESTMENT, OTHER_EXPENSE',
  `name_lo`         VARCHAR(255)    NOT NULL COMMENT 'Lao name from Article 18',
  `name_en`         VARCHAR(255)    DEFAULT NULL,
  `article_ref`     VARCHAR(10)     NOT NULL DEFAULT '18' COMMENT 'Always 18',
  `clause_no`       TINYINT UNSIGNED NOT NULL COMMENT '1-4',
  `is_active`       TINYINT(1)      NOT NULL DEFAULT 1,
  `sort_order`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_expense_type` (`expense_type`),
  UNIQUE KEY `uk_clause` (`article_ref`, `clause_no`),
  KEY `idx_active_order` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Taseng Expense categories per Article 18 (Expenditure)';

-- Insert 4 expense categories from Article 18
INSERT INTO `edoc_expense_type` (`expense_type`, `name_lo`, `name_en`, `article_ref`, `clause_no`, `is_active`, `sort_order`) VALUES
('SALARY',        'ລາຍຈ່າຍເງິນເດືອນ, ເງິນນະໂຍບາຍ ແລະ ເງິນອຸດໜູນຕ່າງໆ', 'Salary, policy payments, and subsidies',                '18', 1, 1, 1),
('ADMIN',         'ລາຍຈ່າຍບໍລິຫານ',                                            'Administrative expenses',                               '18', 2, 1, 2),
('INVESTMENT',    'ລາຍຈ່າຍລົງທຶນ ຂອງລັດ',                                        'State investment expenditures',                         '18', 3, 1, 3),
('OTHER_EXPENSE', 'ລາຍຈ່າຍອື່ນ ຕາມເນື້ອໃນສາລະບານລາຍຈ່າຍງົບປະມານ',         'Other expenditures per budget classification',          '18', 4, 1, 4);

-- Verify
SELECT `id`, `expense_type`, `name_lo`, `name_en`, `article_ref`, `clause_no`, `is_active`, `sort_order`
FROM `edoc_expense_type` ORDER BY `sort_order`;