<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
header('Content-Type: application/json; charset=utf-8');
require '../../connect.php';
$type = $_GET['type'] ?? '';
$id   = $_GET['id'] ?? '';
$allowed = ['province' => 'edoc_district', 'district' => 'edoc_taseng', 'taseng' => 'edoc_village'];
$parentCol = ['province' => 'province_id', 'district' => 'district_id', 'taseng' => 'tasseng_id'];
if (!isset($allowed[$type]) || !$id) { echo json_encode(['status' => 'error', 'message' => 'bad params']); exit; }
try {
    $col = $parentCol[$type];
    $stmt = $conn->prepare("SELECT id, name_lo FROM {$allowed[$type]} WHERE $col = ? ORDER BY id ASC");
    $stmt->execute([$id]);
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
