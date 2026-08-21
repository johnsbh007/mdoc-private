<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
header('Content-Type: application/json; charset=utf-8');
require '../../connect.php';

if (!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }

$doc_id = (int)($_POST['doc_id'] ?? 0);
if ($doc_id <= 0) { echo json_encode(['status'=>'error','message'=>'Missing doc_id']); exit; }

try {
    // load document + its item fee, confirm it is in approved (awaiting payment) state
    $st = $conn->prepare("SELECT d.id, d.status, d.amount, di.price AS item_price
                          FROM edoc_document d
                          LEFT JOIN edoc_doc_item di ON d.doc_item_id = di.id
                          WHERE d.id=?");
    $st->execute([$doc_id]);
    $doc = $st->fetch(PDO::FETCH_ASSOC);
    if (!$doc) { echo json_encode(['status'=>'error','message'=>'Document not found']); exit; }
    if (!in_array($doc['status'], ['ISSUED','PENDING_PAYMENT'], true)) {
        echo json_encode(['status'=>'error','message'=>'Payment evidence can only be uploaded when the document is approved (status: '.$doc['status'].')']); exit;
    }

    // --- validate paid amount against the expected fee (from document amount or item price) ---
    // NOTE: PDO returns DECIMAL as string; "0.00" is truthy in PHP, so compare numerically
    $docAmount   = (float)($doc['amount'] ?? 0);
    $itemPrice   = (float)($doc['item_price'] ?? 0);
    $expectedFee = $docAmount > 0 ? $docAmount : $itemPrice;
    $amountRaw   = trim((string)($_POST['amount'] ?? ''));
    if ($amountRaw === '') {
        echo json_encode(['status'=>'error','message'=>'ກະລຸນາປ້ອນຈຳນວນເງິນທີ່ຈ່າຍ (Amount paid is required)']); exit;
    }
    $amount = (float)$amountRaw;
    if ($amount < 0) { echo json_encode(['status'=>'error','message'=>'ຈຳນວນເງິນບໍ່ຖືກຕ້ອງ (Invalid amount)']); exit; }
    if ($expectedFee > 0 && $amount + 1e-6 < $expectedFee) {
        echo json_encode(['status'=>'error','message'=>'ຈຳນວນເງິນທີ່ຈ່າຍ ('.number_format($amount,0).' ກີບ) ຕໍ່າກວ່າຄ່າທຳນຽມທີ່ກຳນົດ ('.number_format($expectedFee,0).' ກີບ) — ບໍ່ສາມາດຮັບການຊຳລະໄດ້ (Amount paid is less than the required fee; payment not accepted)']); exit;
    }

    // --- validate upload ---
    if (empty($_FILES['slip']) || $_FILES['slip']['error'] !== UPLOAD_ERR_OK) {
        $code = $_FILES['slip']['error'] ?? 'none';
        echo json_encode(['status'=>'error','message'=>'Upload failed (code '.$code.')']); exit;
    }
    $tmp  = $_FILES['slip']['tmp_name'];
    $orig = basename($_FILES['slip']['name']);
    $size = (int)$_FILES['slip']['size'];
    $max  = 8 * 1024 * 1024; // 8 MB
    if ($size <= 0 || $size > $max) { echo json_encode(['status'=>'error','message'=>'File size invalid (max 8 MB)']); exit; }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? finfo_file($finfo, $tmp) : mime_content_type($tmp);
    $allowed = ['image/jpeg','image/png','image/webp','image/gif','application/pdf'];
    if (!in_array($mime, $allowed, true)) { echo json_encode(['status'=>'error','message'=>'Only JPG/PNG/WEBP/GIF/PDF allowed (got '.$mime.')']); exit; }

    // --- store file with a randomized, safe name ---
    $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','application/pdf'=>'pdf'][$mime];
    $relDir = 'files/payment_slips';
    $absDir = dirname(__DIR__, 2) . '/' . $relDir; // /var/www/html/edoc/files/payment_slips
    if (!is_dir($absDir)) { @mkdir($absDir, 0775, true); }
    $fname = 'slip_' . $doc_id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest  = $absDir . '/' . $fname;
    if (!move_uploaded_file($tmp, $dest)) { echo json_encode(['status'=>'error','message'=>'Could not store uploaded file']); exit; }
    @chmod($dest, 0664);

    $relPath = $relDir . '/' . $fname;

    $note   = trim($_POST['note'] ?? '');
    $pay_ref = trim($_POST['pay_ref'] ?? '');

    $conn->beginTransaction();

    // keep only one active evidence per doc: mark existing ones as superseded is not needed; just insert
    $ins = $conn->prepare("INSERT INTO edoc_payment_evidence (doc_id, file_path, orig_name, mime, size, amount, pay_ref, note, uploaded_by) VALUES (?,?,?,?,?,?,?,?,?)");
    $ins->execute([$doc_id, $relPath, $orig, $mime, $size, $amount, $pay_ref, $note, $_SESSION['user_id']]);

    // move status ISSUED -> PENDING_PAYMENT (slip awaiting officer verification)
    if ($doc['status'] === 'ISSUED') {
        $conn->prepare("UPDATE edoc_document SET status='PENDING_PAYMENT' WHERE id=?")->execute([$doc_id]);
        $conn->prepare("INSERT INTO edoc_document_status (doc_id, status, remark, changed_by) VALUES (?,'PENDING_PAYMENT',?,?)")
              ->execute([$doc_id, 'Payment slip uploaded, awaiting verification', $_SESSION['user_id']]);
    }

    $conn->commit();

    echo json_encode(['status'=>'success','message'=>'Payment slip uploaded. Awaiting officer verification.','file'=>$relPath]);
} catch (PDOException $e) {
    if ($conn && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
