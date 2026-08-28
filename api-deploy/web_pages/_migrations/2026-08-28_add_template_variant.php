<?php
/**
 * Migration: add template_variant to edoc_document (biography short/full selector).
 * NULL = default (full multi-page); 'short' = ຊີວະປະຫວັດຫຍໍ້ one-page template.
 * Only used by doc_item 8; other items ignore it. Idempotent.
 */
require __DIR__ . '/../connect.php';

$cols = $conn->query("SHOW COLUMNS FROM edoc_document LIKE 'template_variant'")->fetchAll(PDO::FETCH_ASSOC);
if (empty($cols)) {
    $conn->exec("ALTER TABLE edoc_document ADD COLUMN template_variant VARCHAR(20) NULL DEFAULT NULL AFTER related_id");
    echo "template_variant column added\n";
} else {
    echo "template_variant column already exists\n";
}
echo "DONE\n";