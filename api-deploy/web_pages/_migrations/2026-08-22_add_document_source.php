<?php
/**
 * Migration: add request source indicator to edoc_document (2026-08-22)
 *
 * Team decision: distinguish where a request originated —
 *   OFFICE = officer at the Tasseng office created it via eDoc web
 *   MOBILE = citizen submitted it from the mDoc mobile app (future)
 * Mobile drafts stay on the phone; only submitted requests arrive (as PENDING_APPROVAL).
 *
 * Run: php _migrations/2026-08-22_add_document_source.php
 */
require __DIR__ . '/../connect.php';

echo "Adding source column to edoc_document ...\n";

$exists = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='edoc_document' AND COLUMN_NAME='source'")->fetchColumn();
if ($exists) {
    echo "  column already exists — skipping ALTER\n";
} else {
    $conn->exec("ALTER TABLE edoc_document ADD COLUMN source VARCHAR(10) NOT NULL DEFAULT 'OFFICE' COMMENT 'Request origin: OFFICE (web officer) | MOBILE (citizen mDoc app)' AFTER status");
    echo "  column added\n";
}

$cnt = $conn->query("SELECT source, COUNT(*) FROM edoc_document GROUP BY source")->fetchColumn();
echo "DONE (existing rows default to OFFICE)\n";
