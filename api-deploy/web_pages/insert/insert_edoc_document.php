<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require_once '../connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $citizen_id  = !empty($_POST['citizen_id']) ? (int)$_POST['citizen_id'] : 0;
    $doc_item_id = !empty($_POST['doc_item_id']) ? (int)$_POST['doc_item_id'] : 0;
    $village_id  = !empty($_POST['village_id']) ? (int)$_POST['village_id'] : null;
    $amount      = (float)($_POST['amount'] ?? 0);
    $remark      = trim($_POST['remark'] ?? '');
    $off_id      = $_SESSION['off_id'] ?? '00';
    $created_by  = $_SESSION['user_id'];

    if (empty($citizen_id) || empty($doc_item_id)) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ກະລຸນາເລືອກພົນລະເມືອງ ແລະ ເອກະສານ'];
        header("Location: ../pages/list_edoc_document.php"); exit;
    }
    try {
        $conn->beginTransaction();
        // doc_no: DOC-YYYYMMDD-<seq>
        $seq = $conn->query("SELECT COUNT(*)+1 FROM edoc_document WHERE DATE(created_at)=CURDATE()")->fetchColumn();
        $doc_no = 'DOC-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare("INSERT INTO edoc_document (doc_no, citizen_id, doc_item_id, village_id, off_id, status, amount, created_by, submitted_at) VALUES (?,?,?,?,?,?,?,?,NOW())");
        $stmt->execute([$doc_no, $citizen_id, $doc_item_id, $village_id, $off_id, 'SUBMITTED', $amount, $created_by]);
        $doc_id = $conn->lastInsertId();

        // invoice
        $inv_no = 'INV-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
        $conn->prepare("INSERT INTO edoc_invoice (doc_id, inv_no, amount, currency, status) VALUES (?,?,?,'LAK','UNPAID')")->execute([$doc_id, $inv_no, $amount]);

        // form fields
        foreach ($_POST as $k => $v) {
            if (strpos($k, 'field_') === 0) {
                $field_id = (int)substr($k, 6);
                $conn->prepare("INSERT INTO edoc_form_data (doc_id, field_id, field_value) VALUES (?,?,?)")->execute([$doc_id, $field_id, trim($v)]);
            }
        }
        // status history
        $conn->prepare("INSERT INTO edoc_document_status (doc_id, status, remark, changed_by) VALUES (?, 'SUBMITTED', ?, ?)")->execute([$doc_id, $remark, $created_by]);

        $conn->commit();
        $_SESSION['alert'] = ['type' => 'success', 'message' => "ບັນທຶກຄຳຮ້ອງ $doc_no ສຳເລັດ!"];
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ຜິດພາດ: ' . $e->getMessage()];
    }
    header("Location: ../pages/list_edoc_document.php"); exit;
}