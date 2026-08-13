<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
header('Content-Type: application/json; charset=utf-8');
require '../../connect.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['status'=>'error','message'=>'bad method']); exit; }

$id     = $_POST['id'] ?? '';
$action = $_POST['action'] ?? '';   // approve | reject | pay | issue
$remark = trim($_POST['remark'] ?? '');
$changed_by = $_SESSION['user_id'] ?? 0;
$off_id = $_SESSION['off_id'] ?? '00';

if (empty($id) || empty($action)) { echo json_encode(['status'=>'error','message'=>'Missing params']); exit; }

try {
    $conn->beginTransaction();
    $stmt = $conn->prepare("SELECT * FROM edoc_document WHERE id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$doc) { echo json_encode(['status'=>'error','message'=>'Not found']); $conn->rollBack(); exit; }

    $newStatus = $doc['status'];
    $ok = true; $msg = '';

    switch ($action) {
        case 'approve':
            if ($doc['status'] === 'SUBMITTED') {
                $newStatus = 'PENDING_APPROVAL';
                // staff verifies the submitted data, then it goes to approval
                $conn->prepare("UPDATE edoc_document SET status=?, verified_by=?, verified_at=NOW() WHERE id=?")
                      ->execute([$newStatus, $changed_by, $id]);
                $msg = 'ຢືນຢັນຂໍ້ມູນສຳເລັດ (ສົ່ງອະນຸມັດ)';
            } else if ($doc['status'] === 'PENDING_APPROVAL') {
                $newStatus = 'ISSUED'; // approved -> awaiting payment (per design flow)
                $conn->prepare("UPDATE edoc_document SET status=?, approved_by=?, approved_at=NOW() WHERE id=?")
                      ->execute([$newStatus, $changed_by, $id]);
                $msg = 'ອະນຸມັດສຳເລັດ (ລໍຖ້າການຊຳລະ)';
            } else {
                $ok = false; $msg = 'ບໍ່ສາມາດອະນຸມັດໄດ້ (ສະຖານະປັດຈຸບັນ: '.$doc['status'].')';
            }
            break;
        case 'reject':
            $newStatus='REJECTED';
            $conn->prepare("UPDATE edoc_document SET status=? WHERE id=?")->execute([$newStatus, $id]);
            $msg='ປະຕິເສດຄຳຮ້ອງ';
            break;
        case 'pay':
            if ($doc['status'] !== 'ISSUED') { $ok=false; $msg='ສະຖານະບໍ່ຖືກຕ້ອງສຳລັບການຊຳລະ'; }
            else {
                $method = $_POST['method'] ?? 'CASH';
                $pay_no = 'PAY-' . date('Ymd') . '-' . str_pad($id, 5, '0', STR_PAD_LEFT);
                $conn->prepare("INSERT INTO edoc_payment (invoice_id, pay_no, method, amount, paid_at, status) SELECT id, ?, ?, ?, NOW(), 'PAID' FROM edoc_invoice WHERE doc_id=?")
                      ->execute([$pay_no, $method, $doc['amount'], $id]);
                $conn->prepare("UPDATE edoc_invoice SET status='PAID' WHERE doc_id=?")->execute([$id]);
                // receipt — fetch the payment id we just created (parameterized)
                $pidStmt = $conn->prepare("SELECT p.id FROM edoc_payment p JOIN edoc_invoice i ON p.invoice_id=i.id WHERE i.doc_id=? ORDER BY p.id DESC LIMIT 1");
                $pidStmt->execute([$id]);
                $pid = $pidStmt->fetchColumn();
                $receipt_no = 'RCPT-' . date('Ymd') . '-' . str_pad($id, 5, '0', STR_PAD_LEFT);
                $conn->prepare("INSERT INTO edoc_receipt (payment_id, receipt_no, receipt_date, issued_by) VALUES (?,?,CURDATE(),?)")
                      ->execute([$pid, $receipt_no, $changed_by]);
                $newStatus='PAID';
                $conn->prepare("UPDATE edoc_document SET status=? WHERE id=?")->execute([$newStatus, $id]);
                $msg='ຊຳລະເງິນສຳເລັດ (ໃບເກັບກ້ຽວ '.$receipt_no.')';
            }
            break;
        case 'issue':
            if ($doc['status'] !== 'PAID') { $ok=false; $msg='ຕ້ອງຊຳລະເງິນກ່ອນອອກເອກະສານ'; }
            else {
                $newStatus='HANDOVER';
                $conn->prepare("UPDATE edoc_document SET status=? WHERE id=?")->execute([$newStatus, $id]);
                $msg='ອອກເອກະສານ/ສົ່ງມອບແລ້ວ (HANDOVER)';
            }
            break;
        default:
            $ok=false; $msg='ບໍ່ຮູ້ຈັກການກະທຳ';
    }

    // status history
    if ($ok) {
        $conn->prepare("INSERT INTO edoc_document_status (doc_id, status, remark, changed_by) VALUES (?,?,?,?)")
              ->execute([$id, $newStatus, $remark ?: $msg, $changed_by]);
    }
    $conn->commit();

    if ($ok) echo json_encode(['status'=>'success','message'=>$msg,'new_status'=>$newStatus]);
    else echo json_encode(['status'=>'error','message'=>$msg]);
} catch (PDOException $e) { $conn->rollBack(); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
