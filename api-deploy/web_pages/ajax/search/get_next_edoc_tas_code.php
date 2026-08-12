<?php
require '../../connect.php';
$stmt = $conn->query("SELECT MAX(CAST(tas_code AS UNSIGNED)) as max_id FROM edoc_taseng");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$max = $row['max_id'] ? (int)$row['max_id'] : 0;
$next = str_pad($max + 1, 6, '0', STR_PAD_LEFT);
echo json_encode(['status' => 'success', 'next_code' => $next]);