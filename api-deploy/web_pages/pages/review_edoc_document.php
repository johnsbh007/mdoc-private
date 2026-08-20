<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require_once '../connect.php';

$_current = 'review_edoc_document.php';
$doc_id = (int)($_GET['doc_id'] ?? 0);
if ($doc_id <= 0) { die('Missing doc_id'); }

// ── load document + citizen + item/type + village ─────────────────────
$st = $conn->prepare("SELECT d.*, c.first_name, c.last_name, c.gender, c.dob, c.phone, c.email,
                             c.id_card, c.nationality, c.origin, c.ethnicity, c.religion, c.occupation,
                             c.house_no, c.unit_no, c.address_line, c.id_card_issue_date,
                             c.province_id, c.district_id, c.taseng_id, c.village_id,
                             c.birth_province_id, c.birth_district_id, c.birth_taseng_id, c.birth_village_id,
                             i.name_lo AS item_name, i.doc_item_code, i.price, i.doc_type_id,
                             t.name_lo AS type_name, t.doc_type_code,
                             v.name_lo AS village_name
                      FROM edoc_document d
                      JOIN edoc_doc_item i ON d.doc_item_id = i.id
                      JOIN edoc_doc_type t ON i.doc_type_id = t.id
                      JOIN edoc_citizen c ON d.citizen_id = c.id
                      LEFT JOIN edoc_village v ON c.village_id = v.id
                      WHERE d.id = ?");
$st->execute([$doc_id]);
$doc = $st->fetch(PDO::FETCH_ASSOC);
if (!$doc) { die('Document not found'); }

// ── status labels ──────────────────────────────────────────────────────
$status_labels = [
  'DRAFT'            => 'ຮ່າງ/draft',
  'SUBMITTED'        => 'ຮັບແລ້ວ/submitted',
  'PENDING_APPROVAL' => 'ລໍຖ້າອະນຸມັດ/Pending Approval',
  'NEEDS_REVISION'   => 'ຕ້ອງການປັບປຸງ/Needs Revision',
  'ISSUED'           => 'ອະນຸມັດແລ້ວ/Issued',
  'PENDING_PAYMENT'  => 'ລໍຖ້າຊໍາລະ/Pending Payment',
  'PAID'             => 'ຊໍາລະແລ້ວ/Paid',
  'SIGNED'           => 'ເຊັນແລ້ວ/Signed',
  'HANDOVER'         => 'ສົ່ງໃຫ້ແລ້ວ/Handover',
  'DOWNLOADED'       => 'ດາວໂຫລດແລ້ວ/Downloaded',
  'REJECTED'         => 'ປະຕິເສດ/Rejected',
  'CANCELED'         => 'ຍົກເລີກ/Canceled',
];
$status = $doc['status'];

// ── lookup names (save code, show name) ───────────────────────────────
function resolveName($conn, $table, $code) {
  if ($code === '' || $code === null) return '';
  $st = $conn->prepare("SELECT name_lo FROM $table WHERE code = ? LIMIT 1");
  $st->execute([$code]);
  $n = $st->fetchColumn();
  return $n !== false ? $n : $code;
}
function geoName($conn, $table, $id) {
  if (!$id) return '';
  $st = $conn->prepare("SELECT name_lo FROM $table WHERE id = ? LIMIT 1");
  $st->execute([$id]);
  $n = $st->fetchColumn();
  return $n !== false ? $n : '';
}
function fmtDate($v) { return $v ? date('d/m/Y', strtotime($v)) : ''; }

$nationalityName = resolveName($conn, 'edoc_country',   $doc['nationality']);
$originName      = resolveName($conn, 'edoc_country',   $doc['origin']);
$ethnicityName   = resolveName($conn, 'edoc_ethnicity', $doc['ethnicity']);
$religionName    = resolveName($conn, 'edoc_religion',  $doc['religion']);
$occupationName  = resolveName($conn, 'edoc_occupation',$doc['occupation']);
$provName   = geoName($conn, 'edoc_province', $doc['province_id']);
$distName   = geoName($conn, 'edoc_district', $doc['district_id']);
$tasName    = geoName($conn, 'edoc_taseng',   $doc['taseng_id']);
$vilName    = geoName($conn, 'edoc_village',  $doc['village_id']);
$bProvName  = geoName($conn, 'edoc_province', $doc['birth_province_id']);
$bDistName  = geoName($conn, 'edoc_district', $doc['birth_district_id']);
$bTasName   = geoName($conn, 'edoc_taseng',   $doc['birth_taseng_id']);
$bVilName   = geoName($conn, 'edoc_village',  $doc['birth_village_id']);

// ── saved document fields (edoc_form_data joined with edoc_doc_field) ─
$fields = [];
$fd = $conn->prepare("SELECT f.field_label, f.field_type, fd.field_value
                      FROM edoc_form_data fd
                      JOIN edoc_doc_field f ON fd.field_id = f.id
                      WHERE fd.doc_id = ? ORDER BY f.id");
$fd->execute([$doc_id]);
$fields = $fd->fetchAll(PDO::FETCH_ASSOC);

// ── residence cert detail (doc_type 1) ────────────────────────────────
$res = null;
$pProvName = $pDistName = $pTasName = $pVilName = '';
if ($doc['doc_type_id'] == 1) {
    $rs = $conn->prepare("SELECT * FROM edoc_dc_residence WHERE doc_id = ?");
    $rs->execute([$doc_id]);
    $res = $rs->fetch(PDO::FETCH_ASSOC);
    if ($res) {
        // structured parents address (not stored in edoc_form_data) — resolve geo names
        $pProvName = geoName($conn, 'edoc_province', $res['parents_province_id'] ?? null);
        $pDistName = geoName($conn, 'edoc_district', $res['parents_district_id'] ?? null);
        $pTasName  = geoName($conn, 'edoc_taseng',   $res['parents_taseng_id']  ?? null);
        $pVilName  = geoName($conn, 'edoc_village',  $res['parents_village_id']  ?? null);
    }
}

// ── status history ────────────────────────────────────────────────────
$history = $conn->prepare("SELECT * FROM edoc_document_status WHERE doc_id = ? ORDER BY id DESC LIMIT 10");
$history->execute([$doc_id]);
$history = $history->fetchAll(PDO::FETCH_ASSOC);

// ── invoice ───────────────────────────────────────────────────────────
$inv = $conn->prepare("SELECT inv_no, amount, currency, status FROM edoc_invoice WHERE doc_id = ? ORDER BY id DESC LIMIT 1");
$inv->execute([$doc_id]);
$inv = $inv->fetch(PDO::FETCH_ASSOC);

// ── payment evidence (slip) ───────────────────────────────────────────
$evidence = [];
$ev = $conn->prepare("SELECT * FROM edoc_payment_evidence WHERE doc_id = ? ORDER BY id DESC");
$ev->execute([$doc_id]);
$evidence = $ev->fetchAll(PDO::FETCH_ASSOC);

require '../components/layout.php';
?>
<style>
.rev-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,.07); margin-bottom: 18px; overflow: hidden; }
.rev-card .rev-head { background: #f4f7fb; padding: 10px 18px; font-weight: 700; color: #044482; border-bottom: 1px solid #e8edf4; font-size: .95rem; }
.rev-card .rev-body { padding: 16px 18px; }
.rev-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 8px 20px; }
.rev-grid .lbl { font-size: .78rem; color: #888; margin: 0; }
.rev-grid .val { font-size: .95rem; color: #222; font-weight: 500; margin: 0; }
.st-badge { padding: 4px 12px; border-radius: 20px; font-size: .8rem; font-weight: 600; }
.st-DRAFT { background:#eee; color:#555; } .st-SUBMITTED { background:#e3f2fd; color:#1565c0; }
.st-PENDING_APPROVAL { background:#fff3e0; color:#e65100; } .st-NEEDS_REVISION { background:#fff8e1; color:#b26a00; } .st-ISSUED { background:#e8f5e9; color:#2e7d32; }
.st-PENDING_PAYMENT { background:#fff8e1; color:#f9a825; } .st-PAID { background:#c8e6c9; color:#1b5e20; }
.st-SIGNED { background:#e0f2f1; color:#00695c; } .st-HANDOVER { background:#e1bee7; color:#6a1b9a; }
.st-DOWNLOADED { background:#d1c4e9; color:#4527a0; } .st-REJECTED { background:#ffebee; color:#c62828; }
.st-CANCELED { background:#eceff1; color:#546e7a; }
.btn-act { min-width: 150px; }
</style>

<div class="content-wrapper p-3">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="list_edoc_document.php">ບໍລິການເອກະສານ</a></li>
      <li class="breadcrumb-item"><a href="list_edoc_document.php?status=<?= $status ?>">ລາຍການຄຳຮ້ອງ</a></li>
      <li class="breadcrumb-item active" aria-current="page">ຈັດການ: <?= htmlspecialchars($doc['doc_no']) ?></li>
    </ol>
  </nav>

  <!-- DOC SUMMARY -->
  <div class="rev-card">
    <div class="rev-head d-flex justify-content-between align-items-center">
      <span><i class="fas fa-file-alt"></i> ລາຍລະອຽດຄຳຮ້ອງ (Request Summary)</span>
      <span class="st-badge st-<?= $status ?>"><?= $status_labels[$status] ?? $status ?></span>
    </div>
    <div class="rev-body">
      <div class="rev-grid">
        <div><p class="lbl">ເລກທີເອກະສານ (Doc No)</p><p class="val"><?= htmlspecialchars($doc['doc_no']) ?></p></div>
        <div><p class="lbl">ປະເພດ (Type)</p><p class="val"><?= htmlspecialchars($doc['type_name']) ?> [<?= htmlspecialchars($doc['doc_type_code']) ?>]</p></div>
        <div><p class="lbl">ເອກະສານ (Item)</p><p class="val"><?= htmlspecialchars($doc['item_name']) ?> [<?= htmlspecialchars($doc['doc_item_code']) ?>]</p></div>
        <div><p class="lbl">ຄ່າທຳນຽມ (Fee)</p><p class="val"><?= number_format((float)($doc['amount'] ?: $doc['price']), 0) ?> ກີບ</p></div>
        <div><p class="lbl">ວັນທີສົ່ງ (Submitted)</p><p class="val"><?= fmtDate($doc['submitted_at'] ?: $doc['created_at']) ?></p></div>
        <?php if ($inv): ?>
        <div><p class="lbl">ໃບແຈ້ງໜີ້ (Invoice)</p><p class="val"><?= htmlspecialchars($inv['inv_no']) ?> — <?= $inv['status'] ?></p></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- CITIZEN -->
  <div class="rev-card">
    <div class="rev-head"><i class="fas fa-user"></i> ຂໍ້ມູນພົນລະເມືອງ (Citizen)</div>
    <div class="rev-body">
      <div class="rev-grid">
        <div><p class="lbl">ຊື່ ແລະ ນາມສະກຸນ</p><p class="val"><?= htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) ?></p></div>
        <div><p class="lbl">ເພດ</p><p class="val"><?= $doc['gender'] === 'M' ? 'ຊາຍ' : ($doc['gender'] === 'F' ? 'ຍິງ' : '-') ?></p></div>
        <div><p class="lbl">ເລກບັດປະຈຳຕົວ (NID)</p><p class="val"><?= htmlspecialchars($doc['id_card']) ?></p></div>
        <div><p class="lbl">ວັນເກີດ</p><p class="val"><?= fmtDate($doc['dob']) ?></p></div>
        <div><p class="lbl">ເບີໂທ</p><p class="val"><?= htmlspecialchars($doc['phone']) ?></p></div>
        <div><p class="lbl">ອີເມວ</p><p class="val"><?= htmlspecialchars($doc['email']) ?></p></div>
        <div><p class="lbl">ສັນຊາດ</p><p class="val"><?= htmlspecialchars($nationalityName) ?></p></div>
        <div><p class="lbl">ເຊື້ອຊາດ</p><p class="val"><?= htmlspecialchars($originName) ?></p></div>
        <div><p class="lbl">ຊົນເຜົ່າ</p><p class="val"><?= htmlspecialchars($ethnicityName) ?></p></div>
        <div><p class="lbl">ສາສະໜາ</p><p class="val"><?= htmlspecialchars($religionName) ?></p></div>
        <div><p class="lbl">ອາຊີບ</p><p class="val"><?= htmlspecialchars($occupationName) ?></p></div>
        <div><p class="lbl">ວັນອອກບັດ</p><p class="val"><?= fmtDate($doc['id_card_issue_date']) ?></p></div>
      </div>
      <hr>
      <div class="rev-grid">
        <div><p class="lbl">ທີ່ຢູ່ປັດຈຸບັນ</p>
          <p class="val"><?= htmlspecialchars(implode(' › ', array_filter([$provName, $distName, $tasName, $vilName]))) ?><br>
          <small class="text-muted">ເລກທີ່ <?= htmlspecialchars($doc['house_no']) ?>/<?= htmlspecialchars($doc['unit_no']) ?> <?= htmlspecialchars($doc['address_line']) ?></small></p></div>
        <div><p class="lbl">ສະຖານທີ່ເກີດ</p>
          <p class="val"><?= htmlspecialchars(implode(' › ', array_filter([$bProvName, $bDistName, $bTasName, $bVilName]))) ?></p></div>
      </div>
    </div>
  </div>

  <!-- DOCUMENT FIELDS -->
  <div class="rev-card">
    <div class="rev-head"><i class="fas fa-clipboard-list"></i> ຂໍ້ມູນເອກະສານ (Document Fields)</div>
    <div class="rev-body">
      <?php if ($fields): ?>
      <div class="rev-grid">
        <?php foreach ($fields as $f): ?>
        <div><p class="lbl"><?= htmlspecialchars($f['field_label']) ?></p><p class="val"><?= htmlspecialchars($f['field_value']) ?></p></div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <p class="text-muted mb-0">ຍັງບໍ່ມີຂໍ້ມູນເອກະສານ.</p>
      <?php endif; ?>
      <?php if ($res): ?>
      <hr>
      <!-- Only the structured parents-address geo fields are shown here; the
           basic residence fields (household book no/date, father, mother,
           related authority, purpose) are already rendered by the $fields
           loop above from edoc_form_data — showing them again would duplicate. -->
      <div class="rev-grid">
        <div><p class="lbl">ໜ່ວຍ (Parents Unit)</p><p class="val"><?= htmlspecialchars($res['parents_unit_no'] ?? '') ?></p></div>
        <div><p class="lbl">ທີ່ຢູ່ພໍ່ແມ່ (Parents Address)</p>
          <p class="val"><?= htmlspecialchars(implode(' › ', array_filter([$pProvName, $pDistName, $pTasName, $pVilName]))) ?>
          <?php if (!empty($res['parents_address'])): ?><br><small class="text-muted"><?= htmlspecialchars($res['parents_address']) ?></small><?php endif; ?></p></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- STATUS HISTORY -->
  <div class="rev-card">
    <div class="rev-head"><i class="fas fa-history"></i> ປະຫວັດສະຖານະ (Status History)</div>
    <div class="rev-body">
      <table class="table table-sm mb-0">
        <thead><tr><th>#</th><th>ສະຖານະ</th><th>ໝາຍເຫດ</th><th>ຜູ້ດຳເນີນການ</th><th>ເວລາ</th></tr></thead>
        <tbody>
          <?php foreach ($history as $h): ?>
          <tr>
            <td><?= $h['id'] ?></td>
            <td><span class="st-badge st-<?= $h['status'] ?>"><?= $status_labels[$h['status']] ?? $h['status'] ?></span></td>
            <td><?= htmlspecialchars($h['remark'] ?? '') ?></td>
            <td><?= htmlspecialchars($h['changed_by']) ?></td>
            <td><small><?= $h['created_at'] ?></small></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- PAYMENT EVIDENCE (SLIP) -->
  <?php if (in_array($status, ['ISSUED','PENDING_PAYMENT','PAID'], true)): ?>
  <div class="rev-card">
    <div class="rev-head"><i class="fas fa-file-invoice-dollar"></i> ຫຼັກຖານການຊຳລະ (Payment Evidence / Slip)</div>
    <div class="rev-body">
      <?php if ($evidence): ?>
        <?php foreach ($evidence as $i => $ev): ?>
        <div class="d-flex align-items-start gap-3 mb-3 p-2 border rounded" style="background:#fafbfc;">
          <div class="flex-shrink-0">
            <?php $isImg = in_array($ev['mime'], ['image/jpeg','image/png','image/webp','image/gif'], true); ?>
            <?php if ($isImg): ?>
              <a href="../<?= htmlspecialchars($ev['file_path']) ?>" target="_blank"><img src="../<?= htmlspecialchars($ev['file_path']) ?>" style="width:150px;height:auto;border:1px solid #ddd;border-radius:6px;" alt="slip"></a>
            <?php else: ?>
              <a class="btn btn-outline-secondary" href="../<?= htmlspecialchars($ev['file_path']) ?>" target="_blank"><i class="fas fa-file-pdf"></i> ເບິ່ງໃບສຳລັບ (PDF)</a>
            <?php endif; ?>
          </div>
          <div class="flex-grow-1">
            <div><strong><?= htmlspecialchars($ev['orig_name']) ?></strong> <span class="text-muted">(<?= number_format($ev['size']/1024, 1) ?> KB)</span></div>
            <?php if ($ev['amount'] !== null): ?><div class="text-muted">ຈຳນວນເງິນ (Amount): <?= number_format((float)$ev['amount'], 0) ?> ກີບ</div><?php endif; ?>
            <?php if (!empty($ev['pay_ref'])): ?><div class="text-muted">ເລກອ້າງອີງ (Ref): <?= htmlspecialchars($ev['pay_ref']) ?></div><?php endif; ?>
            <?php if (!empty($ev['note'])): ?><div class="text-muted">ໝາຍເຫດ (Note): <?= htmlspecialchars($ev['note']) ?></div><?php endif; ?>
            <div class="mt-1">
              <?php if ($ev['verified']): ?>
                <span class="badge bg-success"><i class="fas fa-check-circle"></i> ກວດສອບແລ້ວ (Verified)</span>
              <?php else: ?>
                <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> ລໍຖ້າກວດສອບ (Pending Verify)</span>
              <?php endif; ?>
              <span class="text-muted small ms-2"><?= date('d/m/Y H:i', strtotime($ev['created_at'])) ?></span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="text-muted mb-0">ຍັງບໍ່ມີຫຼັກຖານການຊຳລະ (No payment slip uploaded yet).</p>
      <?php endif; ?>

      <?php if ($status === 'ISSUED'): ?>
      <!-- Upload slip form (approved doc, awaiting payment) -->
      <hr>
      <form id="frmSlip" enctype="multipart/form-data">
        <input type="hidden" name="doc_id" value="<?= $doc_id ?>">
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label small mb-1">ເອກະສານຫຼັກຖານ (Slip image/PDF) <span class="text-danger">*</span></label>
            <input type="file" name="slip" id="slipFile" class="form-control form-control-sm" accept="image/*,application/pdf" required>
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">ຈຳນວນເງິນທີ່ຈ່າຍ (ກີບ)</label>
            <input type="number" step="0.01" name="amount" id="slipAmount" class="form-control form-control-sm" placeholder="<?= htmlspecialchars(number_format((float)($doc['amount'] ?: $doc['price']), 0)) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">ເລກອ້າງອີງ (Bank ref)</label>
            <input type="text" name="pay_ref" id="slipRef" class="form-control form-control-sm">
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="button" class="btn btn-primary btn-sm w-100" onclick="uploadSlip()"><i class="fas fa-upload"></i> ອັບໂຫລດ (Upload)</button>
          </div>
        </div>
        <div class="row g-2 mt-1">
          <div class="col-md-8">
            <label class="form-label small mb-1">ໝາຍເຫດ (Note)</label>
            <input type="text" name="note" id="slipNote" class="form-control form-control-sm">
          </div>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ACTIONS -->
  <div class="rev-card">
    <div class="rev-head"><i class="fas fa-tasks"></i> ດຳເນີນການ (Actions)</div>
    <div class="rev-body">
      <div class="mb-3">
        <label class="form-label">ໝາຍເຫດ (Remark / Reject reason)</label>
        <textarea id="actRemark" class="form-control" rows="2" placeholder="ສາເຫດທີ່ປະຕິເສດ ຫຼື ໝາຍເຫດອື່ນໆ..."></textarea>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <?php if ($status === 'SUBMITTED'): ?>
          <button class="btn btn-primary btn-act" onclick="doAction('approve')"><i class="fas fa-check-circle"></i> ຢືນຢັນຂໍ້ມູນ → ອະນຸມັດ</button>
        <?php elseif ($status === 'PENDING_APPROVAL'): ?>
          <button class="btn btn-primary btn-act" onclick="doAction('approve')"><i class="fas fa-check-circle"></i> ອະນຸມັດ (Approve)</button>
        <?php elseif ($status === 'PENDING_PAYMENT'): ?>
          <button class="btn btn-success btn-act" onclick="doAction('pay')"><i class="fas fa-check-double"></i> ຢືນຢັນການຊຳລະ (Confirm Paid)</button>
          <button class="btn btn-danger btn-act" onclick="doAction('reject_payment')"><i class="fas fa-times-circle"></i> ປະຕິເສດສະລິບ (Reject Slip)</button>
        <?php elseif ($status === 'PAID'): ?>
          <button class="btn btn-success btn-act" onclick="doAction('issue')"><i class="fas fa-hand-holding"></i> ສົ່ງມອບ (Handover)</button>
        <?php endif; ?>
        <?php if (in_array($status, ['SUBMITTED','PENDING_APPROVAL','ISSUED','PENDING_PAYMENT'], true)): ?>
          <button class="btn btn-warning btn-act" onclick="doAction('revision')"><i class="fas fa-undo-alt"></i> ຕ້ອງປັບປຸງ (Request Change)</button>
        <?php endif; ?>
        <?php if (in_array($status, ['SUBMITTED','PENDING_APPROVAL','ISSUED'], true)): ?>
          <button class="btn btn-danger btn-act" onclick="doAction('reject')"><i class="fas fa-times-circle"></i> ປະຕິເສດ (Reject)</button>
        <?php endif; ?>
        <?php if ($doc['doc_type_id'] == 1 && in_array($status, ['SUBMITTED','PENDING_APPROVAL','ISSUED','PENDING_PAYMENT','NEEDS_REVISION'], true)): ?>
          <a class="btn btn-outline-secondary btn-act" target="_blank" href="preview_edoc_document.php?doc_id=<?= $doc_id ?>"><i class="fas fa-eye"></i> ສະແດງ (Preview)</a>
        <?php endif; ?>
        <?php if ($status === 'NEEDS_REVISION'): ?>
          <a class="btn btn-secondary btn-act" href="request_doc.php?doc_id=<?= $doc_id ?>&mode=draft"><i class="fas fa-edit"></i> ແກ້ໄຂເອກະສານ (Edit &amp; Re-submit)</a>
        <?php endif; ?>
        <a href="list_edoc_document.php?status=<?= $status ?>" class="btn btn-light border btn-act">ກັບຄືນ (Back)</a>
      </div>
    </div>
  </div>
</div>

<?php require '../components/layout_footer.php'; ?>

<script>
function uploadSlip() {
  const file = document.getElementById('slipFile').files[0];
  if (!file) { Swal.fire('ແຈ້ງເຕືອນ!', 'ກະລຸນາເລືອກເອກະສານຫຼັກຖານ (slip)', 'warning'); return; }
  const fd = new FormData(document.getElementById('frmSlip'));
  Swal.fire({ title: 'ກຳລັງອັບໂຫລດ...', text: 'ກະລຸນາລໍຖ້າ', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
  $.ajax({
    url: '../ajax/action/upload_payment_evidence.php',
    type: 'POST',
    data: fd,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(r) {
      if (r.status === 'success') {
        Swal.fire({ title: 'ສຳເລັດ!', text: r.message, icon: 'success', timer: 1500, showConfirmButton: false }).then(() => location.reload());
      } else {
        Swal.fire('ຜິດພາດ', r.message || 'ອັບໂຫລດບໍ່ສຳເລັດ', 'error');
      }
    },
    error: function() { Swal.fire('ຜິດພາດ', 'ເຊື່ອມຕໍ່ບໍ່ໄດ້!', 'error'); }
  });
}

function doAction(action) {
  const remark = document.getElementById('actRemark').value.trim();
  Swal.fire({
    title: 'ຢືນຢັນການດຳເນີນການ?',
    text: remark ? ('ໝາຍເຫດ: ' + remark) : 'ດຳເນີນການ: ' + action,
    icon: 'question', showCancelButton: true,
    confirmButtonColor: '#044482', cancelButtonColor: '#bbb',
    confirmButtonText: 'ຕົກລົງ', cancelButtonText: 'ຍົກເລີກ'
  }).then((result) => {
    if (!result.isConfirmed) return;
    $.post('../ajax/action/process_edoc_document.php', { id: <?= $doc_id ?>, action, remark }, function(res) {
      let r = res;
      try { if (typeof res === 'string') r = JSON.parse(res); } catch(e) {}
      if (r && r.status === 'success') {
        Swal.fire({ title: 'ສຳເລັດ', text: r.message, icon: 'success', timer: 1500, showConfirmButton: false })
          .then(() => location.reload());
      } else {
        Swal.fire('ຜິດພາດ', (r && r.message) || 'ເກີດຂໍ້ຜິດພາດ', 'error');
      }
    }).fail(function() { Swal.fire('ຜິດພາດ', 'ເຊື່ອມຕໍ່ບໍ່ໄດ້!', 'error'); });
  });
}
</script>
