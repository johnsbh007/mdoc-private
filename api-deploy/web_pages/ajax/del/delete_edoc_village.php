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
        $stmt = $conn->prepare("DELETE FROM edoc_village WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'ລຶບຂໍ້ມູນບ້ານສຳເລັດ']);
    } catch (PDOException $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
}