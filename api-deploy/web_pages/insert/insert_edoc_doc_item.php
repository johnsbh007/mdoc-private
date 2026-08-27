<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require_once '../connect.php';
require_once '../edoc_auth.php';
if (!edoc_can_basic_data()) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['status'=>'error','message'=>'ທ່ານບໍ່ມີສິດດຳເນີນການ (Basic Data) / No permission']); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id           = $_POST['id'] ?? '';
    $doc_type_id  = $_POST['doc_type_id'] ?? '';
    $doc_item_code = trim($_POST['doc_item_code'] ?? '');
    $name_lo      = trim($_POST['name_lo'] ?? '');
    $name_en      = trim($_POST['name_en'] ?? '');
    $unit         = trim($_POST['unit'] ?? '');
    $price        = (float)($_POST['price'] ?? 0);
    $is_active    = (int)($_POST['is_active'] ?? 1);

    if (empty($doc_type_id) || empty($name_lo)) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ກະລຸນາປ້ອນຂໍ້ມູນທີ່ຈຳເປັນ'];
        header("Location: ../pages/list_edoc_doc_item.php"); exit;
    }
    try {
        if (!empty($id)) {
            $stmt = $conn->prepare("UPDATE edoc_doc_item SET doc_type_id=?, doc_item_code=?, name_lo=?, name_en=?, unit=?, price=?, is_active=? WHERE id=?");
            $stmt->execute([$doc_type_id, $doc_item_code, $name_lo, $name_en, $unit, $price, $is_active, $id]);
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'ອັບເດດລາຍການເອກະສານສຳເລັດ!'];
        } else {
            $stmt = $conn->prepare("INSERT INTO edoc_doc_item (doc_type_id, doc_item_code, name_lo, name_en, unit, price, is_active) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$doc_type_id, $doc_item_code, $name_lo, $name_en, $unit, $price, $is_active]);
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'ບັນທຶກລາຍການເອກະສານສຳເລັດ!'];
        }
        header("Location: ../pages/list_edoc_doc_item.php"); exit;
    } catch (PDOException $e) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ຜິດພາດ: ' . $e->getMessage()];
        header("Location: ../pages/list_edoc_doc_item.php"); exit;
    }
}