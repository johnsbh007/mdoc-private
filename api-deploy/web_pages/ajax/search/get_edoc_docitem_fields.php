<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
header('Content-Type: application/json; charset=utf-8');
require '../../connect.php';
$doc_item_id = $_GET['doc_item_id'] ?? '';
if (empty($doc_item_id)) { echo json_encode(['status' => 'error', 'message' => 'Missing doc_item_id']); exit; }
try {
    // price from item, then override from service_fee if present
    $stmt = $conn->prepare("SELECT price FROM edoc_doc_item WHERE id = ?");
    $stmt->execute([$doc_item_id]);
    $price = $stmt->fetchColumn();

    $sf = $conn->prepare("SELECT amount FROM edoc_service_fee WHERE doc_item_id = ? AND is_active=1 ORDER BY id DESC LIMIT 1");
    $sf->execute([$doc_item_id]);
    if ($r = $sf->fetchColumn()) { $price = $r; }

    $fld = $conn->prepare("SELECT f.id, f.field_label, f.field_type FROM edoc_doc_field f JOIN edoc_doc_item di ON f.doc_type_id=di.doc_type_id WHERE di.id=? AND f.is_active=1 ORDER BY f.sort_order ASC");
    $fld->execute([$doc_item_id]);
    $fields = $fld->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => ['price' => $price, 'fields' => $fields]]);
} catch (PDOException $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
