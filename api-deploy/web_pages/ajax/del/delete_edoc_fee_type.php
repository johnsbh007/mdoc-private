<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
header('Content-Type: application/json; charset=utf-8');
require '../../connect.php';
require_once '../../edoc_auth.php';
if (!edoc_can_basic_data()) { echo json_encode(['status'=>'error','message'=>'ທ່ານບໍ່ມີສິດດຳເນີນການ (Basic Data) / No permission']); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    if (empty($id)) { echo json_encode(['status' => 'error', 'message' => 'Missing ID']); exit; }
    try {
        // prevent delete if referenced by service_fee
        $chk = $conn->prepare("SELECT COUNT(*) FROM edoc_service_fee WHERE fee_type_id = ?");
        $chk->execute([$id]);
        if ($chk->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'ບໍ່ສາມາດລຶບໄດ້ ເນື່ອງຈາກມີການໃຊ້ລາຍການນີ້ແລ້ວ']);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM edoc_fee_type WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'ລຶບປະເພດລາຍຮັບສຳເລັດ']);
    } catch (PDOException $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
}