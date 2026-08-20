<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
header('Content-Type: application/json; charset=utf-8');
require '../../connect.php';
if (!isset($_SESSION['user_id'])) { echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) { echo json_encode(['status' => 'error', 'message' => 'Missing ID']); exit; }
    try {
        $conn->beginTransaction();
        $conn->prepare("DELETE FROM edoc_form_data WHERE doc_id=?")->execute([$id]);
        $conn->prepare("DELETE FROM edoc_document_status WHERE doc_id=?")->execute([$id]);
        $conn->prepare("DELETE FROM edoc_invoice WHERE doc_id=?")->execute([$id]);
        $conn->prepare("DELETE FROM edoc_dc_residence WHERE doc_id=?")->execute([$id]);
        $conn->prepare("DELETE FROM edoc_document WHERE id=?")->execute([$id]);
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'ລຶບຄຳຮ້ອງສຳເລັດ']);
    } catch (PDOException $e) { $conn->rollBack(); echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
}