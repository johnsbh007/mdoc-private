<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
header('Content-Type: application/json; charset=utf-8');
require '../../connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing ID']);
        exit;
    }
    try {
        // Check if referenced by districts
        $chk = $conn->prepare("SELECT COUNT(*) FROM edoc_district WHERE province_id = ?");
        $chk->execute([$id]);
        if ($chk->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'ບໍ່ສາມາດລຶບໄດ້ ເນື່ອງຈາກມີເມືອງຂອງແຂວງນີ້']);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM edoc_province WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'ລຶບຂໍ້ມູນແຂວງສຳເລັດ']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}