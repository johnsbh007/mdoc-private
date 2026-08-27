<?php
/**
 * Migration: land-sale buyer as a REAL citizen record.
 * 1. Add edoc_document.buyer_id (FK -> edoc_citizen.id) — the buyer of the land-sale.
 * 2. Deactivate the 13 buyer_* EAV fields of doc_type 14 (buyer is now a citizen record;
 *    the buyer tab saves a citizen, not EAV). Idempotent.
 */
require __DIR__ . '/../connect.php';

$conn->beginTransaction();

// 1. buyer_id column (idempotent: check information_schema first)
$hasCol = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='edoc_document' AND COLUMN_NAME='buyer_id'")->fetchColumn();
if (!$hasCol) {
    $conn->exec("ALTER TABLE edoc_document ADD COLUMN buyer_id BIGINT UNSIGNED NULL AFTER citizen_id,
                 ADD KEY idx_doc_buyer (buyer_id)");
    echo "edoc_document.buyer_id added\n";
} else {
    echo "edoc_document.buyer_id already exists\n";
}

// 2. deactivate buyer_* EAV fields of doc_type 14 (now captured as a citizen)
$n = $conn->prepare("UPDATE edoc_doc_field SET is_active=0
                     WHERE doc_type_id=14 AND field_name LIKE 'buyer_%' AND is_active=1")->execute();
echo "deactivated buyer_* EAV fields (doc_type 14)\n";

$conn->commit();
echo "DONE\n";