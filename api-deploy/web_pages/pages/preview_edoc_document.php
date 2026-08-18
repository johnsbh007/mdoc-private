<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require_once '../connect.php';

$doc_id = (int)($_GET['doc_id'] ?? 0);
if ($doc_id <= 0) { die('Missing doc_id'); }

// ── load doc + citizen + residence (same source as the generate step) ─
$d = $conn->prepare("SELECT d.*, c.*, v.name_lo AS village_name,
                            i.name_lo AS item_name, t.name_lo AS type_name
                     FROM edoc_document d
                     JOIN edoc_citizen c ON d.citizen_id = c.id
                     JOIN edoc_doc_item i ON d.doc_item_id = i.id
                     JOIN edoc_doc_type t ON i.doc_type_id = t.id
                     LEFT JOIN edoc_village v ON c.village_id = v.id
                     WHERE d.id = ?");
$d->execute([$doc_id]);
$row = $d->fetch(PDO::FETCH_ASSOC);
if (!$row) { die('Document not found'); }

$res = null;
$rs = $conn->prepare("SELECT * FROM edoc_dc_residence WHERE doc_id = ?");
$rs->execute([$doc_id]);
$res = $rs->fetch(PDO::FETCH_ASSOC);

// ── resolve lookup codes → Lao names (save code, show name) ─────────
function resolveName($conn, $table, $code) {
  if ($code === '' || $code === null) return '';
  $st = $conn->prepare("SELECT name_lo FROM $table WHERE code = ? LIMIT 1");
  $st->execute([$code]);
  $n = $st->fetchColumn();
  return $n !== false ? $n : $code;
}
function geoName($conn, $table, $id, $fallback) {
  if ($id) {
    $st = $conn->prepare("SELECT name_lo FROM $table WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $n = $st->fetchColumn();
    if ($n !== false) return $n;
  }
  return $fallback ?: '';
}
function fmtDate($v) { return $v ? date('d/m/Y', strtotime($v)) : ''; }

$originName      = resolveName($conn, 'edoc_country',    $row['origin']);
$nationalityName = resolveName($conn, 'edoc_country',    $row['nationality']);
$ethnicityName   = resolveName($conn, 'edoc_ethnicity',  $row['ethnicity']);
$religionName    = resolveName($conn, 'edoc_religion',   $row['religion']);
$occupationName  = resolveName($conn, 'edoc_occupation', $row['occupation']);
$birthVillageName  = geoName($conn, 'edoc_village',  $row['birth_village_id']  ?? null, $row['birth_village']);
$birthDistrictName = geoName($conn, 'edoc_district', $row['birth_district_id'] ?? null, $row['birth_district']);
$birthProvinceName = geoName($conn, 'edoc_province', $row['birth_province_id'] ?? null, $row['birth_province']);
$provName  = geoName($conn, 'edoc_province', $row['province_id'],  null);
$distName  = geoName($conn, 'edoc_district', $row['district_id'],  null);
$tasName   = geoName($conn, 'edoc_taseng',   $row['taseng_id'],    null);
$vilName   = geoName($conn, 'edoc_village',  $row['village_id'],   $row['village_name']);

$title = ($row['gender'] === 'M') ? 'ທ້າວ' : (($row['gender'] === 'F') ? 'ນາງ' : '');
$fullName = trim($title . ' ' . $row['first_name'] . ' ' . $row['last_name']);
$father = $res['father_name'] ?? '';
$mother = $res['mother_name'] ?? '';
$household = ($res['household_book_no'] ?? '');
$address = implode(' › ', array_filter([$provName, $distName, $tasName, $vilName]));
$address .= $row['house_no'] ? ' ເລກທີ່ ' . $row['house_no'] . ($row['unit_no'] ? '/' . $row['unit_no'] : '') : '';
$birthPlace = implode(' › ', array_filter([$birthProvinceName, $birthDistrictName, $birthVillageName]));

$status_labels = [
  'SUBMITTED'=>'ຮັບແລ້ວ/submitted','PENDING_APPROVAL'=>'ລໍຖ້າອະນຸມັດ/Pending Approval',
  'NEEDS_REVISION'=>'ຕ້ອງການປັບປຸງ/Needs Revision','ISSUED'=>'ອະນຸມັດແລ້ວ/Issued',
  'PENDING_PAYMENT'=>'ລໍຖ້າຊໍາລະ/Pending Payment','PAID'=>'ຊໍາລະແລ້ວ/Paid',
  'SIGNED'=>'ເຊັນແລ້ວ/Signed','HANDOVER'=>'ສົ່ງໃຫ້ແລ້ວ/Handover','DOWNLOADED'=>'ດາວໂຫລດແລ້ວ/Downloaded',
];
require '../components/layout.php';
?>
<style>
.preview-paper { background:#fff; border:1px solid #ddd; border-radius:8px; box-shadow:0 4px 16px rgba(0,0,0,.08); max-width:820px; margin:0 auto 18px; padding:38px 48px; font-family:'Noto Sans Lao', 'Phetsarath OT', serif; }
.preview-paper h3 { text-align:center; font-weight:700; margin:0 0 4px; }
.preview-paper .sub { text-align:center; color:#555; margin-bottom:18px; }
.preview-paper .doc-no { text-align:center; color:#044482; font-weight:600; margin-bottom:20px; }
.pv-row { display:flex; margin-bottom:10px; line-height:1.7; }
.pv-row .k { flex:0 0 240px; color:#444; }
.pv-row .v { flex:1; font-weight:500; border-bottom:1px dotted #999; min-height:24px; }
.pv-note { text-align:center; color:#999; font-size:.85rem; margin-top:22px; font-style:italic; }
.pv-fill-line { border-bottom:1px dotted #999; display:inline-block; }
.pv-status { text-align:center; color:#888; font-size:.85rem; margin-top:8px; }
</style>
<div class="content-wrapper p-3">
  <nav class="breadcrumb mb-2">
    <ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="list_edoc_document.php">ບໍລິການເອກະສານ</a></li>
      <li class="breadcrumb-item"><a href="review_edoc_document.php?doc_id=<?= $doc_id ?>">ຈັດການ</a></li>
      <li class="breadcrumb-item active">ສະແດງຕົວຢ່າງ (Preview)</li>
    </ol>
  </nav>

  <div class="text-center mb-3">
    <a class="btn btn-outline-secondary" href="review_edoc_document.php?doc_id=<?= $doc_id ?>"><i class="fas fa-arrow-left"></i> ກັບຄືນ (Back to review)</a>
    <button class="btn btn-outline-primary ms-2" onclick="window.print()"><i class="fas fa-print"></i> ພິມ (Print)</button>
  </div>

  <div class="preview-paper">
    <h3>ໃບຢັ້ງຢືນທີ່ຢູ່</h3>
    <div class="sub">(Residence Certificate — Preview / ຕົວຢ່າງສະແດງ)</div>
    <div class="doc-no">ສະບັບເລກທີ່: <?= htmlspecialchars($row['doc_no']) ?></div>
    <div class="doc-no">ວັນທີ: <?= htmlspecialchars(fmtDate($row['submitted_at'] ?: $row['created_at'])) ?></div>
    <hr>
    <div class="pv-row"><div class="k">ຊື່ ແລະ ນາມສະກຸນ (Full name)</div><div class="v"><?= htmlspecialchars($fullName) ?></div></div>
    <div class="pv-row"><div class="k">ເລກບັດປະຈຳຕົວ (NID)</div><div class="v"><?= htmlspecialchars($row['id_card']) ?></div></div>
    <div class="pv-row"><div class="k">ວັນເດືອນປີເກີດ (DOB)</div><div class="v"><?= htmlspecialchars(fmtDate($row['dob'])) ?></div></div>
    <div class="pv-row"><div class="k">ສັນຊາດ (Nationality)</div><div class="v"><?= htmlspecialchars($nationalityName) ?></div></div>
    <div class="pv-row"><div class="k">ເຊື້ອຊາດ (Origin)</div><div class="v"><?= htmlspecialchars($originName) ?></div></div>
    <div class="pv-row"><div class="k">ຊົນເຜົ່າ (Ethnicity)</div><div class="v"><?= htmlspecialchars($ethnicityName) ?></div></div>
    <div class="pv-row"><div class="k">ສາສະໜາ (Religion)</div><div class="v"><?= htmlspecialchars($religionName) ?></div></div>
    <div class="pv-row"><div class="k">ອາຊີບ (Occupation)</div><div class="v"><?= htmlspecialchars($occupationName) ?></div></div>
    <div class="pv-row"><div class="k">ສະຖານທີ່ເກີດ (Birth place)</div><div class="v"><?= htmlspecialchars($birthPlace) ?></div></div>
    <div class="pv-row"><div class="k">ທີ່ຢູ່ປັດຈຸບັນ (Current address)</div><div class="v"><?= htmlspecialchars($address) ?></div></div>
    <?php if ($father): ?><div class="pv-row"><div class="k">ຊື່ພໍ່ (Father)</div><div class="v"><?= htmlspecialchars($father) ?></div></div><?php endif; ?>
    <?php if ($mother): ?><div class="pv-row"><div class="k">ຊື່ແມ່ (Mother)</div><div class="v"><?= htmlspecialchars($mother) ?></div></div><?php endif; ?>
    <?php if ($household): ?><div class="pv-row"><div class="k">ເລກປື້ມຄຸ້ມບ້ານ (Household book)</div><div class="v"><?= htmlspecialchars($household) ?></div></div><?php endif; ?>
    <hr>
    <div class="pv-row"><div class="k">ຜູ້ອອກໃບຢັ້ງຢືນ</div><div class="v"><span class="pv-fill-line">&nbsp;</span></div></div>
    <div class="pv-note">* ຕົວຢ່າງສະແດງ (Preview) — ເອກະສານຢ່າງເປັນທາງການຈະຖືກອອກຫຼັງຈາກອະນຸມັດ ແລະ ຊຳລະເງິນ*</div>
    <div class="pv-status">ສະຖານະ: <?= $status_labels[$row['status']] ?? $row['status'] ?></div>
  </div>
</div>
<?php require '../components/layout_footer.php'; ?>
