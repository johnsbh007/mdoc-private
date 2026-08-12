<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
require_once '../../connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = $_POST['id'] ?? '';
    $province_id = $_POST['province_id'] ?? '';
    $dist_code   = trim($_POST['dist_code'] ?? '');
    $off_id      = trim($_POST['off_id'] ?? '');
    $dist_nm_l   = trim($_POST['dist_nm_l'] ?? '');
    $dist_nm_e   = trim($_POST['dist_nm_e'] ?? '');

    if (empty($province_id) || empty($dist_code) || empty($dist_nm_l)) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ກະລຸນາປ້ອນຂໍ້ມູນທີ່ຈຳເປັນ'];
        header("Location: ../pages/list_edoc_district.php"); exit;
    }

    try {
        if (!empty($id)) {
            $stmt = $conn->prepare("UPDATE edoc_district SET province_id=?, dist_code=?, off_id=?, name_lo=?, name_en=? WHERE id=?");
            $stmt->execute([$province_id, $dist_code, $off_id, $dist_nm_l, $dist_nm_e, $id]);
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'ອັບເດດຂໍ້ມູນເມືອງ (eDoc) ສຳເລັດ!'];
        } else {
            $chk = $conn->prepare("SELECT COUNT(*) FROM edoc_district WHERE province_id=? AND dist_code=?");
            $chk->execute([$province_id, $dist_code]);
            if ($chk->fetchColumn() > 0) {
                $_SESSION['alert'] = ['type' => 'error', 'message' => 'ລະຫັດເມືອງນີ້ມີໃນລະບົບແລ້ວ!'];
                header("Location: ../pages/list_edoc_district.php"); exit;
            }
            $stmt = $conn->prepare("INSERT INTO edoc_district (province_id, dist_code, off_id, name_lo, name_en) VALUES (?,?,?,?,?)");
            $stmt->execute([$province_id, $dist_code, $off_id, $dist_nm_l, $dist_nm_e]);
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'ບັນທຶກຂໍ້ມູນເມືອງ (eDoc) ສຳເລັດ!'];
        }
        header("Location: ../pages/list_edoc_district.php"); exit;
    } catch (PDOException $e) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ຜິດພາດ: ' . $e->getMessage()];
        header("Location: ../pages/list_edoc_district.php"); exit;
    }
}