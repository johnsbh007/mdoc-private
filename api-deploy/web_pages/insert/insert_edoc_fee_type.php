<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
require_once '../../connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = $_POST['id'] ?? '';
    $fee_type   = trim($_POST['fee_type'] ?? '');
    $article_ref = trim($_POST['article_ref'] ?? '');
    $clause_no  = (int)($_POST['clause_no'] ?? 0);
    $name_lo    = trim($_POST['name_lo'] ?? '');
    $name_en    = trim($_POST['name_en'] ?? '');
    $is_active  = (int)($_POST['is_active'] ?? 1);

    if (empty($fee_type) || empty($name_lo)) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ກະລຸນາປ້ອນຂໍ້ມູນທີ່ຈຳເປັນ'];
        header("Location: ../pages/list_edoc_fee_type.php"); exit;
    }

    try {
        if (!empty($id)) {
            $stmt = $conn->prepare("UPDATE edoc_fee_type SET fee_type=?, article_ref=?, clause_no=?, name_lo=?, name_en=?, is_active=? WHERE id=?");
            $stmt->execute([$fee_type, $article_ref, $clause_no, $name_lo, $name_en, $is_active, $id]);
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'ອັບເດດປະເພດລາຍຮັບສຳເລັດ!'];
        } else {
            $chk = $conn->prepare("SELECT COUNT(*) FROM edoc_fee_type WHERE fee_type=?");
            $chk->execute([$fee_type]);
            if ($chk->fetchColumn() > 0) {
                $_SESSION['alert'] = ['type' => 'error', 'message' => 'ລະຫັດນີ້ມີໃນລະບົບແລ້ວ!'];
                header("Location: ../pages/list_edoc_fee_type.php"); exit;
            }
            $stmt = $conn->prepare("INSERT INTO edoc_fee_type (fee_type, article_ref, clause_no, name_lo, name_en, is_active) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$fee_type, $article_ref, $clause_no, $name_lo, $name_en, $is_active]);
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'ບັນທຶກປະເພດລາຍຮັບສຳເລັດ!'];
        }
        header("Location: ../pages/list_edoc_fee_type.php"); exit;
    } catch (PDOException $e) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ຜິດພາດ: ' . $e->getMessage()];
        header("Location: ../pages/list_edoc_fee_type.php"); exit;
    }
}