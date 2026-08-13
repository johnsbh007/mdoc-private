<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
require_once '../connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = $_POST['id'] ?? '';
    $prov_code = trim($_POST['prov_code'] ?? '');
    $off_id   = trim($_POST['off_id'] ?? '');
    $prov_nm_l = trim($_POST['prov_nm_l'] ?? '');
    $prov_nm_e = trim($_POST['prov_nm_e'] ?? '');

    if (empty($prov_code) || empty($prov_nm_l)) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ກະລຸນາປ້ອນຂໍ້ມູນທີ່ຈຳເປັນ'];
        header("Location: ../pages/list_edoc_province.php"); exit;
    }

    try {
        if (!empty($id)) {
            $stmt = $conn->prepare("UPDATE edoc_province SET prov_code=?, off_id=?, name_lo=?, name_en=? WHERE id=?");
            $stmt->execute([$prov_code, $off_id, $prov_nm_l, $prov_nm_e, $id]);
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'ອັບເດດຂໍ້ມູນແຂວງ (eDoc) ສຳເລັດ!'];
        } else {
            $chk = $conn->prepare("SELECT COUNT(*) FROM edoc_province WHERE prov_code=?");
            $chk->execute([$prov_code]);
            if ($chk->fetchColumn() > 0) {
                $_SESSION['alert'] = ['type' => 'error', 'message' => 'ລະຫັດແຂວງນີ້ມີໃນລະບົບແລ້ວ!'];
                header("Location: ../pages/list_edoc_province.php"); exit;
            }
            $stmt = $conn->prepare("INSERT INTO edoc_province (prov_code, off_id, name_lo, name_en) VALUES (?,?,?,?)");
            $stmt->execute([$prov_code, $off_id, $prov_nm_l, $prov_nm_e]);
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'ບັນທຶກຂໍ້ມູນແຂວງ (eDoc) ສຳເລັດ!'];
        }
        header("Location: ../pages/list_edoc_province.php"); exit;
    } catch (PDOException $e) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ຜິດພາດ: ' . $e->getMessage()];
        header("Location: ../pages/list_edoc_province.php"); exit;
    }
}