<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
require_once '../../connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = $_POST['id'] ?? '';
    $district_id = $_POST['district_id'] ?? '';
    $tas_code    = trim($_POST['tas_code'] ?? '');
    $ncode       = trim($_POST['ncode'] ?? '');
    $tas_nm_l    = trim($_POST['tas_nm_l'] ?? '');
    $tas_nm_e    = trim($_POST['tas_nm_e'] ?? '');

    if (empty($district_id) || empty($tas_code) || empty($tas_nm_l)) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ກະລຸນາປ້ອນຂໍ້ມູນທີ່ຈຳເປັນ'];
        header("Location: ../pages/list_edoc_taseng.php"); exit;
    }

    try {
        if (!empty($id)) {
            $stmt = $conn->prepare("UPDATE edoc_taseng SET district_id=?, tas_code=?, ncode=?, name_lo=?, name_en=? WHERE id=?");
            $stmt->execute([$district_id, $tas_code, $ncode, $tas_nm_l, $tas_nm_e, $id]);
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'ອັບເດດຂໍ້ມູນຕາແສງ (eDoc) ສຳເລັດ!'];
        } else {
            $chk = $conn->prepare("SELECT COUNT(*) FROM edoc_taseng WHERE district_id=? AND tas_code=?");
            $chk->execute([$district_id, $tas_code]);
            if ($chk->fetchColumn() > 0) {
                $_SESSION['alert'] = ['type' => 'error', 'message' => 'ລະຫັດຕາແສງນີ້ມີໃນລະບົບແລ້ວ!'];
                header("Location: ../pages/list_edoc_taseng.php"); exit;
            }
            // Auto ncode if blank
            if (empty($ncode)) $ncode = $tas_code;
            $stmt = $conn->prepare("INSERT INTO edoc_taseng (district_id, tas_code, ncode, name_lo, name_en) VALUES (?,?,?,?,?)");
            $stmt->execute([$district_id, $tas_code, $ncode, $tas_nm_l, $tas_nm_e]);
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'ບັນທຶກຂໍ້ມູນຕາແສງ (eDoc) ສຳເລັດ!'];
        }
        header("Location: ../pages/list_edoc_taseng.php"); exit;
    } catch (PDOException $e) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ຜິດພາດ: ' . $e->getMessage()];
        header("Location: ../pages/list_edoc_taseng.php"); exit;
    }
}