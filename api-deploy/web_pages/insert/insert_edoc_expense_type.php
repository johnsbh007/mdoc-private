<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require_once '../connect.php';
require_once '../edoc_auth.php';
if (!edoc_can_basic_data()) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['status'=>'error','message'=>'ທ່ານບໍ່ມີສິດດຳເນີນການ (Basic Data) / No permission']); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id           = $_POST['id'] ?? '';
    $expense_type = trim($_POST['expense_type'] ?? '');
    $article_ref  = trim($_POST['article_ref'] ?? '');
    $clause_no    = (int)($_POST['clause_no'] ?? 0);
    $name_lo      = trim($_POST['name_lo'] ?? '');
    $name_en      = trim($_POST['name_en'] ?? '');
    $is_active    = (int)($_POST['is_active'] ?? 1);

    if (empty($expense_type) || empty($name_lo)) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ກະລຸນາປ້ອນຂໍ້ມູນທີ່ຈຳເປັນ'];
        header("Location: ../pages/list_edoc_expense_type.php"); exit;
    }

    try {
        if (!empty($id)) {
            $stmt = $conn->prepare("UPDATE edoc_expense_type SET expense_type=?, article_ref=?, clause_no=?, name_lo=?, name_en=?, is_active=? WHERE id=?");
            $stmt->execute([$expense_type, $article_ref, $clause_no, $name_lo, $name_en, $is_active, $id]);
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'ອັບເດດປະເພດລາຍຈ່າຍສຳເລັດ!'];
        } else {
            $chk = $conn->prepare("SELECT COUNT(*) FROM edoc_expense_type WHERE expense_type=?");
            $chk->execute([$expense_type]);
            if ($chk->fetchColumn() > 0) {
                $_SESSION['alert'] = ['type' => 'error', 'message' => 'ລະຫັດນີ້ມີໃນລະບົບແລ້ວ!'];
                header("Location: ../pages/list_edoc_expense_type.php"); exit;
            }
            $stmt = $conn->prepare("INSERT INTO edoc_expense_type (expense_type, article_ref, clause_no, name_lo, name_en, is_active) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$expense_type, $article_ref, $clause_no, $name_lo, $name_en, $is_active]);
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'ບັນທຶກປະເພດລາຍຈ່າຍສຳເລັດ!'];
        }
        header("Location: ../pages/list_edoc_expense_type.php"); exit;
    } catch (PDOException $e) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ຜິດພາດ: ' . $e->getMessage()];
        header("Location: ../pages/list_edoc_expense_type.php"); exit;
    }
}