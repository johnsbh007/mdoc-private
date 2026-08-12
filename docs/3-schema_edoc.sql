-- ============================================================================
-- e-Doc (Electronic Document) component schema — edoc_ tables
-- Database : edoc_tasaeng (MySQL, apis.com.la)
-- Purpose  : New Module 1 electronic-document tables, coexisting with the
--            existing budget/finance module.
-- Prefix   : All new tables use the edoc_ prefix (distinct from the budget
--            module's acc_/gen_/payment_/ap_ prefixes).
-- Links    : Administrative units via off_id -> ap_office; revenue accounts
--            via ac_code -> acc_code.
-- ============================================================================
SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 2.1 Master / Reference data
-- ----------------------------------------------------------------------------

-- Document categories (14: certification/application/proposal/loss/memorandum/
--                        contract/sale/ownership-transfer/fine/permit/change-of-use/
--                        mediation/registration)
CREATE TABLE IF NOT EXISTS `edoc_doc_type` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doc_type_code`   VARCHAR(10)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
                    COMMENT 'Category code 01..14',
  `name_lo`         VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
                    COMMENT 'Category name (Lao)',
  `name_en`         VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                    COMMENT 'Category name (English)',
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_oc_doc_type_code` (`doc_type_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Document categories';

-- Document item catalog (the 51 documents from the requirements, with price & unit)
CREATE TABLE IF NOT EXISTS `edoc_doc_item` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doc_type_id`     BIGINT UNSIGNED NOT NULL COMMENT 'Parent category (FK edoc_doc_type.id)',
  `doc_item_code`   VARCHAR(20)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                    COMMENT 'Document number (DocID)',
  `name_lo`         VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
                    COMMENT 'Document name (Lao)',
  `name_en`         VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                    COMMENT 'Document name (English)',
  `unit`            VARCHAR(20)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                    COMMENT 'Pricing unit (kip/form, kip/set, kip/person...)',
  `price`           DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Standard fee (LAK)',
  `ac_code`         VARCHAR(20)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                    COMMENT 'Revenue account (FK acc_code.ac_code), used when posting fee',
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_oc_item_type` (`doc_type_id`),
  KEY `idx_oc_item_code` (`doc_item_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Document item catalog';

-- Service / handling fee table (per-unit and per-category fee, may override catalog price)
CREATE TABLE IF NOT EXISTS `edoc_service_fee` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doc_item_id`     BIGINT UNSIGNED NOT NULL COMMENT 'FK edoc_doc_item.id',
  `fee_type_id`     BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK edoc_fee_type.id',
  `amount`          DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Service fee amount',
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_oc_svc_item` (`doc_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Service / handling fees';

-- Fee types (Income/Revenue categories per Article 17)
CREATE TABLE IF NOT EXISTS `edoc_fee_type` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fee_type`        VARCHAR(50)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
                    COMMENT 'Fee type code (LAND_TAX/SERVICE_FEE/CONTRIBUTION/RENTAL/FINE/OTHER_INCOME)',
  `name_lo`         VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
                    COMMENT 'Fee type name (Lao) - per Article 17',
  `name_en`         VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                    COMMENT 'Fee type name (English)',
  `article_ref`     VARCHAR(10)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '17'
                    COMMENT 'Law article reference (17 for revenue)',
  `clause_no`       TINYINT UNSIGNED DEFAULT NULL
                    COMMENT 'Clause number within article (1-6)',
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_oc_fee_type` (`fee_type`),
  KEY `idx_article_clause` (`article_ref`, `clause_no`),
  KEY `idx_active_order` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Income/Revenue types per Article 17 (6 categories)';

-- Expense types (Expenditure categories per Article 18)
CREATE TABLE IF NOT EXISTS `edoc_expense_type` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `expense_type`    VARCHAR(50)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
                    COMMENT 'Expense type code (SALARY/ADMIN/INVESTMENT/OTHER_EXPENSE)',
  `name_lo`         VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
                    COMMENT 'Expense type name (Lao) - per Article 18',
  `name_en`         VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                    COMMENT 'Expense type name (English)',
  `article_ref`     VARCHAR(10)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '18'
                    COMMENT 'Law article reference (18 for expenditure)',
  `clause_no`       TINYINT UNSIGNED NOT NULL
                    COMMENT 'Clause number within article (1-4)',
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_expense_type` (`expense_type`),
  UNIQUE KEY `uk_clause` (`article_ref`, `clause_no`),
  KEY `idx_active_order` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Expenditure types per Article 18 (4 categories)';

-- ----------------------------------------------------------------------------
-- 2.2 Administrative hierarchy (Province -> District -> Tasseng -> Village)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `edoc_province` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `prov_code`    VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_lo`      VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en`      VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `off_id`       VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                 COMMENT 'FK ap_office.off_id',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_oc_prov_code` (`prov_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Province';

CREATE TABLE IF NOT EXISTS `edoc_district` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `province_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK edoc_province.id',
  `dist_code`   VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_lo`     VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en`     VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `off_id`      VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_oc_dist_code` (`dist_code`),
  KEY `idx_oc_dist_prov` (`province_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='District';

CREATE TABLE IF NOT EXISTS `edoc_taseng` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `district_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK edoc_district.id',
  `tas_code`    VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ncode`       VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'New code: prov(2)+dist(2)+taseng(2) = 6 digits',
  `name_lo`     VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en`     VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `off_id`      VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_oc_tas_dist_code` (`district_id`, `tas_code`),
  UNIQUE KEY `idx_oc_tas_ncode` (`ncode`),
  KEY `idx_oc_tas_dist` (`district_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Taseng (sub-district level)';

CREATE TABLE IF NOT EXISTS `edoc_village` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tasseng_id`  BIGINT UNSIGNED NOT NULL COMMENT 'FK edoc_taseng.id',
  `vil_code`    VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ncode`       VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'New code: prov(2)+dist(2)+taseng(2)+village(2) = 8 digits',
  `name_lo`     VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en`     VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `off_id`      VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                COMMENT 'FK ap_office.off_id (village service point)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_oc_vil_code` (`vil_code`),
  UNIQUE KEY `idx_oc_vil_ncode` (`ncode`),
  KEY `idx_oc_vil_tas` (`tasseng_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Village';

-- ----------------------------------------------------------------------------
-- 2.3 Citizens / applicants
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `edoc_citizen` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `phone`           VARCHAR(20)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                    COMMENT 'Registration phone number',
  `email`           VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                    COMMENT 'Registration email',
  `password_hash`   VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name`       VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_card`         VARCHAR(50)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                    COMMENT 'ID card number',
  `village_id`      BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK edoc_village.id',
  `dob`             DATE DEFAULT NULL,
  `gender`          VARCHAR(1)   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_oc_cit_phone` (`phone`),
  KEY `idx_oc_cit_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Citizen / applicant';

-- ----------------------------------------------------------------------------
-- 2.4 Document application workflow
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `edoc_document` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doc_no`          VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
                    COMMENT 'Application number',
  `citizen_id`      BIGINT UNSIGNED NOT NULL COMMENT 'Applicant (FK edoc_citizen.id)',
  `doc_item_id`     BIGINT UNSIGNED NOT NULL COMMENT 'FK edoc_doc_item.id',
  `village_id`      BIGINT UNSIGNED DEFAULT NULL COMMENT 'Village of application (for roll-up stats)',
  `off_id`          VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                    COMMENT 'Service office (FK ap_office.off_id)',
  `status`          VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SUBMITTED'
                    COMMENT 'Status: SUBMITTED/PENDING_APPROVAL/PENDING_PAYMENT/PAID/CANCELED',
  `amount`          DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount due for this application',
  `related_id`      BIGINT UNSIGNED DEFAULT NULL COMMENT 'Related document (e.g. referral)',
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `created_by`      BIGINT UNSIGNED DEFAULT NULL,
  `approved_by`     BIGINT UNSIGNED DEFAULT NULL,
  `submitted_at`    DATETIME DEFAULT NULL,
  `approved_at`     DATETIME DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_oc_doc_no` (`doc_no`),
  KEY `idx_oc_doc_citizen` (`citizen_id`),
  KEY `idx_oc_doc_status` (`status`),
  KEY `idx_oc_doc_off` (`off_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Document application (core)';

-- Status / workflow log
CREATE TABLE IF NOT EXISTS `edoc_document_status` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doc_id`        BIGINT UNSIGNED NOT NULL COMMENT 'FK edoc_document.id',
  `status`        VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remark`        VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changed_by`    BIGINT UNSIGNED DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_oc_docst_doc` (`doc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Document status / workflow log';

-- Signatory / approver (per category or level, signers 1..N)
CREATE TABLE IF NOT EXISTS `edoc_document_signer` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doc_type_id`   BIGINT UNSIGNED DEFAULT NULL COMMENT 'By category (NULL = any)',
  `village_id`    BIGINT UNSIGNED DEFAULT NULL COMMENT 'By admin level (optional)',
  `off_id`        VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signer_order`  INT NOT NULL DEFAULT 1 COMMENT 'Signature order 1..5',
  `signer_name`   VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `signer_title`  VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                  COMMENT 'Position',
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_oc_doc_sig_type` (`doc_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Document signatories/approvers';

-- Configurable per-category fields
CREATE TABLE IF NOT EXISTS `edoc_doc_field` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doc_type_id`   BIGINT UNSIGNED NOT NULL COMMENT 'FK edoc_doc_type.id',
  `field_name`    VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_label`   VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_type`    VARCHAR(20)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TEXT'
                  COMMENT 'TEXT/DATE/NUMBER/DROPDOWN',
  `is_active`     TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order`    INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_oc_field_type` (`doc_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Document field configuration';

-- Citizen-entered field values
CREATE TABLE IF NOT EXISTS `edoc_form_data` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doc_id`        BIGINT UNSIGNED NOT NULL COMMENT 'FK edoc_document.id',
  `field_id`      BIGINT UNSIGNED NOT NULL COMMENT 'FK edoc_doc_field.id',
  `field_value`   TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Field value',
  PRIMARY KEY (`id`),
  KEY `idx_oc_form_doc` (`doc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Citizen-entered form data';

-- ----------------------------------------------------------------------------
-- 2.5 Fees & payments
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `edoc_invoice` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doc_id`        BIGINT UNSIGNED NOT NULL COMMENT 'FK edoc_document.id',
  `inv_no`        VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount`        DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount due',
  `currency`      VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LAK',
  `inv_date`      DATE DEFAULT NULL,
  `status`        VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UNPAID'
                  COMMENT 'UNPAID/PAID/CANCELED',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_oc_inv_no` (`inv_no`),
  KEY `idx_oc_inv_doc` (`doc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Invoice (per document)';

CREATE TABLE IF NOT EXISTS `edoc_payment` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id`    BIGINT UNSIGNED NOT NULL COMMENT 'FK edoc_invoice.id',
  `pay_no`        VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `method`        VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'QR'
                  COMMENT 'QR / CASH / BANK',
  `qr_token`      VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                  COMMENT 'QR payment token',
  `amount`        DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount paid',
  `paid_at`       DATETIME DEFAULT NULL,
  `ref_no`        VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                  COMMENT 'Payment channel transaction ref',
  `status`        VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING'
                  COMMENT 'PENDING/PAID/FAILED/REFUND',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_oc_pay_no` (`pay_no`),
  KEY `idx_oc_pay_inv` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment records';

CREATE TABLE IF NOT EXISTS `edoc_receipt` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id`    BIGINT UNSIGNED NOT NULL COMMENT 'FK edoc_payment.id',
  `receipt_no`    VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `receipt_date`  DATE DEFAULT NULL,
  `issued_by`     BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_oc_receipt_no` (`receipt_no`),
  KEY `idx_oc_receipt_pay` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Official receipts';

-- Payment -> accounting booking (posts fee to budget accounts acc_code)
CREATE TABLE IF NOT EXISTS `edoc_payment_acct_mapping` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id`     BIGINT UNSIGNED NOT NULL COMMENT 'FK edoc_payment.id',
  `debit_account`  VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                   COMMENT 'Debit account (FK acc_code.ac_code) cash/bank',
  `credit_account` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                   COMMENT 'Credit account (FK acc_code.ac_code) revenue',
  `amount`         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `ref_journal_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Budget journal ID if already posted',
  `posted_at`      DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oc_pam_pay` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment -> account booking mapping';

-- ----------------------------------------------------------------------------
-- 2.6 File download & archive
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `edoc_file_store` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doc_id`        BIGINT UNSIGNED NOT NULL COMMENT 'FK edoc_document.id',
  `file_path`     VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `signed_by`     VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version`       INT NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_oc_file_doc` (`doc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Issued document files';

CREATE TABLE IF NOT EXISTS `edoc_download_log` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doc_id`        BIGINT UNSIGNED NOT NULL COMMENT 'FK edoc_document.id',
  `file_id`       BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK edoc_file_store.id',
  `downloaded_by` BIGINT UNSIGNED DEFAULT NULL,
  `downloaded_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oc_dl_doc` (`doc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Download log';