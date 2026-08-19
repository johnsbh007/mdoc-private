-- Payment evidence (slip) upload & verification
CREATE TABLE IF NOT EXISTS `edoc_payment_evidence` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doc_id`        BIGINT UNSIGNED NOT NULL COMMENT 'FK edoc_document.id',
  `file_path`     VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
                  COMMENT 'Stored slip file path (within files/payment_slips/)',
  `orig_name`     VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                  COMMENT 'Original uploaded filename',
  `mime`          VARCHAR(80)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size`          INT UNSIGNED DEFAULT 0,
  `amount`        DECIMAL(15,2) DEFAULT NULL COMMENT 'Amount stated on slip, if captured',
  `pay_ref`       VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
                  COMMENT 'Payment/bank reference number, if stated',
  `note`          VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified`      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=pending verify, 1=verified',
  `verified_by`   BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK acc_user.id (officer who verified)',
  `verified_at`   DATETIME DEFAULT NULL,
  `uploaded_by`   BIGINT UNSIGNED DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pe_doc` (`doc_id`),
  KEY `idx_pe_verified` (`verified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment slip / evidence uploads';
