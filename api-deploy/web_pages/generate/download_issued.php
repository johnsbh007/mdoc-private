<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require_once '../connect.php';

$doc_id = (int)($_GET['doc_id'] ?? 0);
if ($doc_id <= 0) { die('Missing doc_id'); }

// latest stored issued file for this doc
$st = $conn->prepare("SELECT f.*, d.doc_no, d.status AS doc_status
                      FROM edoc_file_store f
                      JOIN edoc_document d ON d.id = f.doc_id
                      WHERE f.doc_id = ?
                      ORDER BY f.version DESC, f.id DESC LIMIT 1");
$st->execute([$doc_id]);
$f = $st->fetch(PDO::FETCH_ASSOC);
if (!$f) { die('No issued document stored for this request yet.'); }

$abs = dirname(__DIR__, 2) . '/' . $f['file_path'];
if (!file_exists($abs)) { die('Stored file is missing on disk.'); }

// log the download
$dl = $conn->prepare("INSERT INTO edoc_download_log (doc_id, file_id, downloaded_by, downloaded_at) VALUES (?,?,?,NOW())");
$dl->execute([$doc_id, $f['id'], $_SESSION['user_id']]);

// mark DOWNLOADED (deliverable stages only; never downgrade PAID before issue)
if (in_array($f['doc_status'], ['PAID','SIGNED','HANDOVER','DOWNLOADED'], true) && $f['doc_status'] !== 'DOWNLOADED') {
    $conn->prepare("UPDATE edoc_document SET status='DOWNLOADED' WHERE id=?")->execute([$doc_id]);
}

// name: use the stored filename (ascii-safe), keep the original Lao for the display header
$fname = basename($f['file_path']);
header('Pragma: public');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Content-Length: ' . filesize($abs));
readfile($abs);
exit;
