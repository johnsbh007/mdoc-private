<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require '../connect.php';

// Reports are visible to every logged-in role (team decision 2026-08-22)
$_current = 'report_statistics.php';
$alert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);

$f_from = $_GET['f_from'] ?? date('Y-01-01');
$f_to   = $_GET['f_to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_from)) $f_from = date('Y-01-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_to))   $f_to   = date('Y-m-d');

// ── headline stats ───────────────────────────────────────────────────
$sql = "SELECT COUNT(*) AS total_docs,
               SUM(CASE WHEN status='DRAFT' THEN 1 ELSE 0 END) AS cnt_draft,
               SUM(CASE WHEN status='PENDING_APPROVAL' THEN 1 ELSE 0 END) AS cnt_pending,
               SUM(CASE WHEN status='ISSUED' THEN 1 ELSE 0 END) AS cnt_issued,
               SUM(CASE WHEN status='PENDING_PAYMENT' THEN 1 ELSE 0 END) AS cnt_paypending,
               SUM(CASE WHEN status='PAID' THEN 1 ELSE 0 END) AS cnt_paid,
               SUM(CASE WHEN status='SIGNED' THEN 1 ELSE 0 END) AS cnt_signed,
               SUM(CASE WHEN status='HANDOVER' THEN 1 ELSE 0 END) AS cnt_handover,
               SUM(CASE WHEN status IN ('REJECTED','CANCELED') THEN 1 ELSE 0 END) AS cnt_rejected
        FROM edoc_document WHERE created_at >= ? AND created_at <= ? + INTERVAL 1 DAY";
$st = $conn->prepare($sql);
$st->execute([$f_from, $f_to]);
$h = $st->fetch();

// ── by doc type ──────────────────────────────────────────────────────
$sqlType = "SELECT COALESCE(dt.name_lo,'—') AS type_name, COUNT(*) AS cnt
            FROM edoc_document d
            LEFT JOIN edoc_doc_item di ON d.doc_item_id = di.id
            LEFT JOIN edoc_doc_type dt ON di.doc_type_id = dt.id
            WHERE d.created_at >= ? AND d.created_at <= ? + INTERVAL 1 DAY
            GROUP BY dt.id, dt.name_lo ORDER BY cnt DESC";
$st = $conn->prepare($sqlType); $st->execute([$f_from, $f_to]);
$byType = $st->fetchAll(PDO::FETCH_ASSOC);

// ── by village ───────────────────────────────────────────────────────
$sqlVil = "SELECT COALESCE(v.name_lo,'—') AS village_name, COUNT(*) AS cnt
           FROM edoc_document d
           LEFT JOIN edoc_citizen c ON d.citizen_id = c.id
           LEFT JOIN edoc_village v ON c.village_id = v.id
           WHERE d.created_at >= ? AND d.created_at <= ? + INTERVAL 1 DAY
           GROUP BY v.id, v.name_lo ORDER BY cnt DESC LIMIT 20";
$st = $conn->prepare($sqlVil); $st->execute([$f_from, $f_to]);
$byVillage = $st->fetchAll(PDO::FETCH_ASSOC);

// ── monthly trend ────────────────────────────────────────────────────
$sqlMonth = "SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym, COUNT(*) AS cnt
             FROM edoc_document WHERE created_at >= ? AND created_at <= ? + INTERVAL 1 DAY
             GROUP BY ym ORDER BY ym ASC";
$st = $conn->prepare($sqlMonth); $st->execute([$f_from, $f_to]);
$byMonth = $st->fetchAll(PDO::FETCH_ASSOC);
$maxMonth = 1; foreach ($byMonth as $m) $maxMonth = max($maxMonth, (int)$m['cnt']);

require '../components/layout.php';
?>
<style>
.rpt-card { background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.07); padding:18px; }
.rpt-filter { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin-bottom:14px; }
.rpt-filter .f { display:flex; flex-direction:column; gap:3px; font-size:.82rem; color:#555; }
.rpt-filter input, .rpt-filter button { border:1px solid #ddd; border-radius:8px; padding:7px 12px; font-size:.9rem; outline:none; }
.rpt-filter input:focus { border-color:#044482; }
.rpt-total { display:flex; gap:14px; margin-bottom:14px; flex-wrap:wrap; }
.rpt-total .box { flex:1; min-width:110px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 16px; }
.rpt-total .box .num { font-size:1.2rem; font-weight:700; color:#044482; }
.rpt-total .box .lbl { font-size:.75rem; color:#64748b; }
.rpt-table { width:100%; border-collapse:collapse; font-size:.9rem; }
.rpt-table th { background:#f1f5f9; color:#334155; text-align:left; padding:9px 12px; border-bottom:2px solid #e2e8f0; white-space:nowrap; }
.rpt-table td { padding:8px 12px; border-bottom:1px solid #eef2f7; }
.rpt-table tr:hover td { background:#f8fafc; }
.rpt-print { border:1px solid #ddd; border-radius:8px; background:#fff; padding:7px 14px; font-size:.85rem; cursor:pointer; }
.mini-bar { height:12px; background:#2563eb; border-radius:3px; min-width:2px; display:inline-block; vertical-align:middle; }
@media print { .rpt-filter, .rpt-print, .sidebar, .navbar, .footer { display:none !important; } .rpt-card { box-shadow:none; } }
</style>

<div class="container-fluid py-3">
  <h5 class="mb-1"><i class="fas fa-chart-pie text-info"></i> ລາຍງານສະຖິຕິເອກະສານ (Document Statistics)</h5>
  <div class="text-muted mb-3" style="font-size:.82rem">ຈາກ <?= htmlspecialchars($f_from) ?> ຫາ <?= htmlspecialchars($f_to) ?></div>

  <form class="rpt-filter" method="get" action="report_statistics.php">
    <div class="f"><label>ວັນທີເລີ່ມ (From)</label><input type="date" name="f_from" value="<?= htmlspecialchars($f_from) ?>"></div>
    <div class="f"><label>ວັນທີສິ້ນສຸດ (To)</label><input type="date" name="f_to" value="<?= htmlspecialchars($f_to) ?>"></div>
    <button type="submit" class="btn btn-primary" style="border:none"><i class="fas fa-filter"></i> ກັ່ນຕອງ</button>
    <button type="button" class="rpt-print" onclick="window.print()"><i class="fas fa-print"></i> ພິມ</button>
  </form>

  <div class="rpt-total">
    <div class="box"><div class="num"><?= number_format((int)$h['total_docs']) ?></div><div class="lbl">ທັງໝົດ (Total)</div></div>
    <div class="box"><div class="num"><?= number_format((int)$h['cnt_draft']) ?></div><div class="lbl">ຮ່າງ (Draft)</div></div>
    <div class="box"><div class="num"><?= number_format((int)$h['cnt_pending']) ?></div><div class="lbl">ລໍຖ້າອະນຸມັດ</div></div>
    <div class="box"><div class="num"><?= number_format((int)$h['cnt_issued']) ?></div><div class="lbl">ອະນຸມັດແລ້ວ</div></div>
    <div class="box"><div class="num"><?= number_format((int)$h['cnt_paid']) ?></div><div class="lbl">ຊຳລະແລ້ວ</div></div>
    <div class="box"><div class="num"><?= number_format((int)$h['cnt_signed']) ?></div><div class="lbl">ເຊັນແລ້ວ</div></div>
    <div class="box"><div class="num"><?= number_format((int)$h['cnt_handover']) ?></div><div class="lbl">ສົ່ງມອບແລ້ວ</div></div>
    <div class="box"><div class="num" style="color:#dc2626"><?= number_format((int)$h['cnt_rejected']) ?></div><div class="lbl">ບໍ່ຜ່ານ/ຍົກເລີກ</div></div>
  </div>

  <div class="row g-3">
    <div class="col-md-5">
      <div class="rpt-card">
        <h6 class="mb-2"><i class="fas fa-file-alt"></i> ຕາມປະເພດເອກະສານ (By doc type)</h6>
        <?php if (!$byType): ?><div class="text-muted small py-2">ບໍ່ມີຂໍ້ມູນ</div>
        <?php else: ?>
        <table class="rpt-table">
          <tbody>
          <?php foreach ($byType as $r): ?>
            <tr><td><?= htmlspecialchars($r['type_name']) ?></td><td class="text-end"><?= number_format((int)$r['cnt']) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-md-7">
      <div class="rpt-card">
        <h6 class="mb-2"><i class="fas fa-building"></i> 20 ບ້ານທີ່ມີຄຳຮ້ອງຫຼາຍທີ່ສຸດ (Top villages)</h6>
        <?php if (!$byVillage): ?><div class="text-muted small py-2">ບໍ່ມີຂໍ້ມູນ</div>
        <?php else: ?>
        <table class="rpt-table">
          <tbody>
          <?php foreach ($byVillage as $r): ?>
            <tr><td><?= htmlspecialchars($r['village_name']) ?></td><td class="text-end"><?= number_format((int)$r['cnt']) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="rpt-card mt-3">
    <h6 class="mb-2"><i class="fas fa-chart-line"></i> ສະຖິຕິລາຍເດືອນ (Monthly trend)</h6>
    <?php if (!$byMonth): ?><div class="text-muted small py-2">ບໍ່ມີຂໍ້ມູນ</div>
    <?php else: ?>
    <table class="rpt-table">
      <tbody>
      <?php foreach ($byMonth as $r): ?>
        <tr>
          <td style="width:90px"><?= htmlspecialchars($r['ym']) ?></td>
          <td>
            <span class="mini-bar" style="width:<?= max(2, round(((int)$r['cnt'])/$maxMonth*100)) ?>%"></span>
            <span class="ms-2"><?= number_format((int)$r['cnt']) ?></span>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php require '../components/layout_footer.php'; ?>