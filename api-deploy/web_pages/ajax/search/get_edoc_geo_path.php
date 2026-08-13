<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
header('Content-Type: application/json; charset=utf-8');
require '../../connect.php';
$village_id = $_GET['village_id'] ?? '';
if (!$village_id) { echo json_encode(['status' => 'error', 'message' => 'Missing village_id']); exit; }
try {
    $stmt = $conn->prepare("SELECT v.id AS village_id, v.tasseng_id AS taseng_id, t.district_id, d.province_id
                            FROM edoc_village v
                            JOIN edoc_taseng t ON v.tasseng_id = t.id
                            JOIN edoc_district d ON t.district_id = d.id
                            WHERE v.id = ?");
    $stmt->execute([$village_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $row ? json_encode(['status' => 'success', 'data' => $row]) : json_encode(['status' => 'error', 'message' => 'Not found']);
} catch (PDOException $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
