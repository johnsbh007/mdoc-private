<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
header('Content-Type: application/json; charset=utf-8');
require '../../connect.php';
// Reverse geo lookup: given a village_id, return its parent chain
// (province_id, district_id, taseng_id) + names, for editing an existing doc.
$village_id = $_GET['village_id'] ?? '';
if (!ctype_digit((string)$village_id)) { echo json_encode(['status'=>'error','message'=>'bad params']); exit; }
try {
    $stmt = $conn->prepare("SELECT v.id AS village_id, v.name_lo AS village_name,
                                   t.id AS taseng_id, t.name_lo AS taseng_name,
                                   d.id AS district_id, d.name_lo AS district_name,
                                   p.id AS province_id, p.name_lo AS province_name
                            FROM edoc_village v
                            JOIN edoc_taseng t ON v.tasseng_id = t.id
                            JOIN edoc_district d ON t.district_id = d.id
                            JOIN edoc_province p ON d.province_id = p.id
                            WHERE v.id = ?");
    $stmt->execute([$village_id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) { echo json_encode(['status'=>'error','message'=>'village not found']); exit; }
    echo json_encode(['status'=>'success', 'data' => $r]);
} catch (PDOException $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }