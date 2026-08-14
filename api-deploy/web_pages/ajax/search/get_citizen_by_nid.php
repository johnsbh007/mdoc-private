<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
header('Content-Type: application/json; charset=utf-8');
require '../../connect.php';

$nid = trim($_GET['nid'] ?? '');
if (empty($nid)) { echo json_encode(['status' => 'error', 'message' => 'Missing nid']); exit; }

try {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, full_name, id_card, gender, dob, phone, email,
                                   nationality, origin, ethnicity, religion, occupation,
                                   village_id, address_line, birth_village, birth_district,
                                   birth_province, id_card_issue_date, house_no, unit_no
                            FROM edoc_citizen WHERE id_card = ? LIMIT 1");
    $stmt->execute([$nid]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($c) {
        echo json_encode(['status' => 'success', 'data' => $c]);
    } else {
        echo json_encode(['status' => 'not_found', 'message' => 'ບໍ່ພົບພົນລະເມືອງທີ່ມີເລກບັດນີ້']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
