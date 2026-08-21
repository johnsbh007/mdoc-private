<?php
// Migration: create edoc_document_handover (handover register — recipient name / national ID / date).
// Two-step handover flow (2026-08-21): PAID -> SIGNED (ເຊັນແລ້ວ) -> HANDOVER (ສົ່ງມອບ) with recipient info.
// Run once on the server, then delete.
require '/var/www/html/edoc/connect.php';
$conn->exec("SET NAMES utf8mb4");

$conn->exec("CREATE TABLE IF NOT EXISTS edoc_document_handover (
  doc_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  recipient_name VARCHAR(200) DEFAULT NULL,
  recipient_id_card VARCHAR(50) DEFAULT NULL,
  handover_date DATE DEFAULT NULL,
  changed_by VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "OK: edoc_document_handover created\n";
