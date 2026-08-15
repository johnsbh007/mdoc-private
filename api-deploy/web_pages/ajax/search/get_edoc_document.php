<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('ACCOUNT_SATHA_SESS');
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
require '../../connect.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    if (empty($id)) { echo json_encode(['status' => 'error', 'message' => 'Missing ID']); exit; }
    try {
        $stmt = $conn->prepare("SELECT d.*, di.name_lo AS item_name, di.doc_item_code, c.first_name, c.last_name, c.full_name, c.phone, c.email, c.id_card, c.gender, c.dob, c.address_line, c.village_id AS citizen_village_id,
                                v.name_lo AS village_name, t.name_lo AS taseng_name,
                                vu.full_name AS verified_by_name, au.full_name AS approved_by_name
                                FROM edoc_document d
                                JOIN edoc_doc_item di ON d.doc_item_id = di.id
                                JOIN edoc_citizen c ON d.citizen_id = c.id
                                LEFT JOIN edoc_village v ON d.village_id = v.id
                                LEFT JOIN edoc_taseng t ON v.tasseng_id = t.id
                                LEFT JOIN ap_users vu ON d.verified_by = vu.id
                                LEFT JOIN ap_users au ON d.approved_by = au.id
                                WHERE d.id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$doc) { echo json_encode(['status' => 'error', 'message' => 'Not found']); exit; }

        // form data
        $fd = $conn->prepare("SELECT f.id AS field_id, f.field_label, fd.field_value FROM edoc_form_data fd JOIN edoc_doc_field f ON fd.field_id=f.id WHERE fd.doc_id=?");
        $fd->execute([$id]);
        $form = $fd->fetchAll(PDO::FETCH_ASSOC);

        // status history
        $sh = $conn->prepare("SELECT * FROM edoc_document_status WHERE doc_id=? ORDER BY id ASC");
        $sh->execute([$id]);
        $history = $sh->fetchAll(PDO::FETCH_ASSOC);

        // invoice / payment
        $inv = $conn->prepare("SELECT * FROM edoc_invoice WHERE doc_id=?");
        $inv->execute([$id]);
        $invoice = $inv->fetch(PDO::FETCH_ASSOC);

        $remarkStmt = $conn->prepare("SELECT remark FROM edoc_document_status WHERE doc_id=? ORDER BY id DESC LIMIT 1");
        $remarkStmt->execute([$id]);
        $latestRemark = $remarkStmt->fetchColumn();

        echo json_encode(['status' => 'success', 'data' => ['doc' => $doc, 'form' => $form, 'history' => $history, 'invoice' => $invoice, 'latest_remark' => $latestRemark]]);
    } catch (PDOException $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
}