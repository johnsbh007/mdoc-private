<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require_once '../connect.php';
require_once '../edoc_auth.php';
if (!edoc_can_basic_data()) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['status'=>'error','message'=>'ທ່ານບໍ່ມີສິດດຳເນີນການ (Basic Data) / No permission']); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = $_POST['id'] ?? '';
    $tasseng_id  = $_POST['tasseng_id'] ?? '';
    $vil_code    = trim($_POST['vil_code'] ?? '');
    $ncode       = trim($_POST['ncode'] ?? '');
    $vil_nm_l    = trim($_POST['vil_nm_l'] ?? '');
    $vil_nm_e    = trim($_POST['vil_nm_e'] ?? '');

    if (empty($tasseng_id) || empty($vil_code) || empty($vil_nm_l)) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ກະລຸນາປ້ອນຂໍ້ມູນທີ່ຈຳເປັນ'];
        header("Location: ../pages/list_edoc_village.php"); exit;
    }

    try {
        if (!empty($id)) {
            $stmt = $conn->prepare("UPDATE edoc_village SET tasseng_id=?, vil_code=?, ncode=?, name_lo=?, name_en=? WHERE id=?");
            $stmt->execute([$tasseng_id, $vil_code, $ncode, $vil_nm_l, $vil_nm_e, $id]);
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'ອັບເດດຂໍ້ມູນບ້ານ (eDoc) ສຳເລັດ!'];
        } else {
            $chk = $conn->prepare("SELECT COUNT(*) FROM edoc_village WHERE tasseng_id=? AND vil_code=?");
            $chk->execute([$tasseng_id, $vil_code]);
            if ($chk->fetchColumn() > 0) {
                $_SESSION['alert'] = ['type' => 'error', 'message' => 'ລະຫັດບ້ານນີ້ມີໃນລະບົບແລ້ວ!'];
                header("Location: ../pages/list_edoc_village.php"); exit;
            }
            if (empty($ncode)) $ncode = $vil_code;
            $stmt = $conn->prepare("INSERT INTO edoc_village (tasseng_id, vil_code, ncode, name_lo, name_en) VALUES (?,?,?,?,?)");
            $stmt->execute([$tasseng_id, $vil_code, $ncode, $vil_nm_l, $vil_nm_e]);
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'ບັນທຶກຂໍ້ມູນບ້ານ (eDoc) ສຳເລັດ!'];
        }
        header("Location: ../pages/list_edoc_village.php"); exit;
    } catch (PDOException $e) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ຜິດພາດ: ' . $e->getMessage()];
        header("Location: ../pages/list_edoc_village.php"); exit;
    }
}