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
            // SUBMITTED merged into PENDING_APPROVAL: submission goes straight to approval queue
            if ($doc['status'] === 'PENDING_APPROVAL') {
                $newStatus = 'ISSUED'; // approved -> awaiting payment (per design flow)
                $conn->prepare("UPDATE edoc_document SET status=?, approved_by=?, approved_at=NOW() WHERE id=?")
                      ->execute([$newStatus, $changed_by, $id]);
                $msg = 'ອະນຸມັດສຳເລັດ (ລໍຖ້າການຊຳລະ)';
            } else {
                $ok = false; $msg = 'ບໍ່ສາມາດອະນຸມັດໄດ້ (ສະຖານະປັດຈຸບັນ: '.$doc['status'].')';
            }
            break;
        case 'reject_payment':
            // Reject the uploaded slip: return doc to ISSUED so a valid slip can be re-uploaded.
            if ($doc['status'] !== 'PENDING_PAYMENT') { $ok=false; $msg='ສະຖານະບໍ່ຖືກຕ້ອງສຳລັບການປະຕິເສດສະລິບ'; }
            else {
                $newStatus='ISSUED';
                $conn->prepare("UPDATE edoc_document SET status=? WHERE id=?")->execute([$newStatus, $id]);
                $msg='ປະຕິເສດສະລິບການຊຳລະ (ກັບໄປລໍຖ້າການຊຳລະ — ກະລຸນາອັບໂຫລດໃບສຳລັບໃໝ່)';
            }
            break;
        case 'reject':
            if (!in_array($doc['status'], ['PENDING_APPROVAL','ISSUED','PENDING_PAYMENT','NEEDS_REVISION'], true)) {
                $ok=false; $msg='ບໍ່ສາມາດປະຕິເສດໄດ້ (ສະຖານະປັດຈຸບັນ: '.$doc['status'].')';
            } else {
                $newStatus='REJECTED';
                $conn->prepare("UPDATE edoc_document SET status=? WHERE id=?")->execute([$newStatus, $id]);
                $msg='ປະຕິເສດຄຳຮ້ອງ';
            }
            break;
        case 'revision':
            // Request changes: doc returns to an editable state so it can be corrected & re-submitted.
            if (!in_array($doc['status'], ['PENDING_APPROVAL','ISSUED','PENDING_PAYMENT','NEEDS_REVISION'], true)) {
                $ok=false; $msg='ບໍ່ສາມາດຂໍປັບປຸງໄດ້ (ສະຖານະປັດຈຸບັນ: '.$doc['status'].')';
            } else {
                $newStatus='NEEDS_REVISION';
                $conn->prepare("UPDATE edoc_document SET status=? WHERE id=?")->execute([$newStatus, $id]);
                $msg='ຂໍໃຫ້ປັບປຸງຂໍ້ມູນ (ສົ່ງຄືນໃຫ້ແກ້ໄຂ)';
            }
            break;
        case 'pay':
            if ($doc['status'] !== 'PENDING_PAYMENT') { $ok=false; $msg='ສະຖານະບໍ່ຖືກຕ້ອງສຳລັບການຢືນຢັນການຊຳລະ (ຕ້ອງ PENDING_PAYMENT)'; }
            else {
                $method = trim($_POST['method'] ?? '');
                if ($method === '') $method = 'BANK'; // slip => typically bank transfer / QR
                $pay_no = 'PAY-' . date('Ymd') . '-' . str_pad($id, 5, '0', STR_PAD_LEFT);
                $conn->prepare("INSERT INTO edoc_payment (invoice_id, pay_no, method, amount, paid_at, status) SELECT id, ?, ?, ?, NOW(), 'PAID' FROM edoc_invoice WHERE doc_id=?")
                      ->execute([$pay_no, $method, $doc['amount'], $id]);
                $conn->prepare("UPDATE edoc_invoice SET status='PAID' WHERE doc_id=?" )->execute([$id]);
                // mark the latest evidence as verified
                $conn->prepare("UPDATE edoc_payment_evidence SET verified=1, verified_by=?, verified_at=NOW() WHERE doc_id=? AND verified=0")
                      ->execute([$changed_by, $id]);
                // receipt — fetch the payment id we just created (parameterized)
                $pidStmt = $conn->prepare("SELECT p.id FROM edoc_payment p JOIN edoc_invoice i ON p.invoice_id=i.id WHERE i.doc_id=? ORDER BY p.id DESC LIMIT 1");
                $pidStmt->execute([$id]);
                $pid = $pidStmt->fetchColumn();
                $receipt_no = 'RCPT-' . date('Ymd') . '-' . str_pad($id, 5, '0', STR_PAD_LEFT);
                $conn->prepare("INSERT INTO edoc_receipt (payment_id, receipt_no, receipt_date, issued_by) VALUES (?,?,CURDATE(),?)")
                      ->execute([$pid, $receipt_no, $changed_by]);
                $newStatus='PAID';
                $conn->prepare("UPDATE edoc_document SET status=? WHERE id=?" )->execute([$newStatus, $id]);
                $msg='ຢືນຢັນການຊຳລະສຳເລັດ (ໃບເກັບກ້ຽວ '.$receipt_no.')';
            }
            break;
        case 'sign':
            // PAID -> SIGNED: officer signs the printed document (no data entry)
            if ($doc['status'] !== 'PAID') { $ok=false; $msg='ຕ້ອງຊຳລະເງິນກ່ອນເຊັນເອກະສານ'; }
            else {
                $newStatus='SIGNED';
                $conn->prepare("UPDATE edoc_document SET status=? WHERE id=?")->execute([$newStatus, $id]);
                $msg='ເຊັນເອກະສານແລ້ວ (SIGNED)';
            }
            break;
        case 'issue':
            // SIGNED -> HANDOVER: record who received the document (recipient name / national ID / date)
            if ($doc['status'] !== 'SIGNED') { $ok=false; $msg='ຕ້ອງເຊັນເອກະສານກ່ອນສົ່ງມອບ'; }
            else {
                $recipientName = trim($_POST['recipient_name'] ?? '');
                $recipientId   = trim($_POST['recipient_id_card'] ?? '');
                $handoverDate  = trim($_POST['handover_date'] ?? '');
                if ($recipientName === '') { $ok=false; $msg='ກະລຸນາປ້ອນຊື່ຜູ້ຮັບ (recipient name)'; }
                else {
                    if ($handoverDate === '') $handoverDate = date('Y-m-d');
                    $conn->prepare("INSERT INTO edoc_document_handover (doc_id, recipient_name, recipient_id_card, handover_date, changed_by) VALUES (?,?,?,?,?)")
                          ->execute([$id, $recipientName, $recipientId, $handoverDate, $changed_by]);
                    $newStatus='HANDOVER';
                    $conn->prepare("UPDATE edoc_document SET status=? WHERE id=?")->execute([$newStatus, $id]);
                    $msg='ສົ່ງມອບເອກະສານໃຫ້ '.$recipientName.' ແລ້ວ (HANDOVER)';
                }
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
