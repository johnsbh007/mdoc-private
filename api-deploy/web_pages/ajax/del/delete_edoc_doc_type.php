<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
header('Content-Type: application/json; charset=utf-8');
require '../../connect.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    if (empty($id)) { echo json_encode(['status' => 'error', 'message' => 'Missing ID']); exit; }
    try {
        $chk = $conn->prepare("SELECT COUNT(*) FROM edoc_doc_item WHERE doc_type_id = ?");
        $chk->execute([$id]);
        if ($chk->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'ບໍ່ສາມາດລຶບໄດ້ ເນື່ອງຈາກມີລາຍການເອກະສານຂອງປະເພດນີ້']);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM edoc_doc_type WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'ລຶບປະເພດເອກະສານສຳເລັດ']);
    } catch (PDOException $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
}