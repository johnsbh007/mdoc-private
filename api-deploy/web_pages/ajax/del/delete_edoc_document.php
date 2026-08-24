<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
header('Content-Type: application/json; charset=utf-8');
require '../../connect.php';
if (!isset($_SESSION['user_id'])) { echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // capability gate: only users allowed to delete may remove a request
    require_once '../../edoc_auth.php';
    edoc_require_cap('can_delete', 'ລຶບຄຳຮ້ອງ');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) { echo json_encode(['status' => 'error', 'message' => 'Missing ID']); exit; }
    try {
        // remove any stored issued files on disk (best-effort)
        $files = $conn->prepare("SELECT file_path FROM edoc_file_store WHERE doc_id=?");
        $files->execute([$id]);
        foreach ($files->fetchAll(PDO::FETCH_COLUMN) as $rel) {
            $abs = dirname(__DIR__, 2) . '/' . $rel;
            if (is_file($abs)) @unlink($abs);
        }
        $conn->beginTransaction();
        $conn->prepare("DELETE FROM edoc_download_log WHERE doc_id=?")->execute([$id]);
        $conn->prepare("DELETE FROM edoc_file_store WHERE doc_id=?")->execute([$id]);
        $conn->prepare("DELETE FROM edoc_form_data WHERE doc_id=?")->execute([$id]);
        $conn->prepare("DELETE FROM edoc_document_status WHERE doc_id=?")->execute([$id]);
        $conn->prepare("DELETE FROM edoc_invoice WHERE doc_id=?")->execute([$id]);
        $conn->prepare("DELETE FROM edoc_dc_residence WHERE doc_id=?")->execute([$id]);
        $conn->prepare("DELETE FROM edoc_document WHERE id=?")->execute([$id]);
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'ລຶບຄຳຮ້ອງສຳເລັດ']);
    } catch (PDOException $e) { $conn->rollBack(); echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
}