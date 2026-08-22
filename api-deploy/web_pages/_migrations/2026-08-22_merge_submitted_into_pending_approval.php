<?php
/**
 * Migration: merge SUBMITTED into PENDING_APPROVAL (2026-08-22)
 *
 * Team decision: when an officer completes a draft and clicks submit, the request
 * goes straight to PENDING_APPROVAL — no separate "received/verify" step.
 *
 * 1. Move existing SUBMITTED documents to PENDING_APPROVAL.
 * 2. Change the column default so any future insert without an explicit status
 *    lands in PENDING_APPROVAL instead of SUBMITTED.
 *
 * Run: php _migrations/2026-08-22_merge_submitted_into_pending_approval.php
 */
require __DIR__ . '/../connect.php';

echo "Migrating SUBMITTED -> PENDING_APPROVAL ...\n";

$moved = $conn->exec("UPDATE edoc_document SET status='PENDING_APPROVAL' WHERE status='SUBMITTED'");
echo "  edoc_document rows moved: $moved\n";

$conn->exec("ALTER TABLE edoc_document ALTER COLUMN status SET DEFAULT 'PENDING_APPROVAL'");
echo "  column default updated\n";

$left = $conn->query("SELECT COUNT(*) FROM edoc_document WHERE status='SUBMITTED'")->fetchColumn();
echo "  remaining SUBMITTED rows: $left\n";

echo ($left == 0) ? "DONE\n" : "WARNING: rows remain\n";
