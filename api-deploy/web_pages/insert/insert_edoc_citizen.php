<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require_once '../connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = $_POST['id'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $gender     = $_POST['gender'] ?? '';
    $dob        = $_POST['dob'] ?? null;
    $id_card    = trim($_POST['id_card'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $village_id = !empty($_POST['village_id']) ? (int)$_POST['village_id'] : null;
    $address_line = trim($_POST['address_line'] ?? '');
    $full_name  = trim($first_name . ' ' . $last_name);

    if (empty($first_name) || empty($last_name) || empty($phone)) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ກະລຸນາປ້ອນຊື່ ແລະ ເບີໂທ'];
        header("Location: ../pages/list_edoc_citizen.php"); exit;
    }
    $password_hash = password_hash(substr($phone, -6) ?: 'changeme', PASSWORD_DEFAULT);
    try {
        if (!empty($id)) {
            $stmt = $conn->prepare("UPDATE edoc_citizen SET first_name=?, last_name=?, full_name=?, gender=?, dob=?, id_card=?, phone=?, email=?, village_id=?, address_line=? WHERE id=?");
            $stmt->execute([$first_name, $last_name, $full_name, $gender ?: null, $dob ?: null, $id_card ?: null, $phone, $email ?: null, $village_id, $address_line ?: null, $id]);
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'ອັບເດດພົນລະເມືອງສຳເລັດ!'];
        } else {
            $stmt = $conn->prepare("INSERT INTO edoc_citizen (phone, email, password_hash, full_name, first_name, last_name, id_card, village_id, address_line, dob, gender, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,1)");
            $stmt->execute([$phone, $email ?: null, $password_hash, $full_name, $first_name, $last_name, $id_card ?: null, $village_id, $address_line ?: null, $dob ?: null, $gender ?: null]);
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'ບັນທຶກພົນລະເມືອງສຳເລັດ!'];
        }
        header("Location: ../pages/list_edoc_citizen.php"); exit;
    } catch (PDOException $e) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ຜິດພາດ: ' . $e->getMessage()];
        header("Location: ../pages/list_edoc_citizen.php"); exit;
    }
}