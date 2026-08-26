<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require '../connect.php';

// Reports are visible to every logged-in role (team decision 2026-08-22)
$_current = 'report_service.php';
$alert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);

// ── filters: date range (default: current year) ─────────────────────
$f_from = $_GET['f_from'] ?? date('Y-01-01');
$f_to   = $_GET['f_to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_from)) $f_from = date('Y-01-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_to))   $f_to   = date('Y-m-d');

// ── service funnel by month ──────────────────────────────────────────
// created   = docs submitted (doc created) in period
// approved  = docs that reached ISSUED in period (status history)
// paid      = payments confirmed (edoc_payment PAID) in period
// signed    = docs that reached SIGNED in period (status history)
// handed    = docs that reached HANDOVER in period (status history or handover table)
$sql = "SELECT ym,
               SUM(created)  AS created_cnt,
               SUM(approved) AS approved_cnt,
               SUM(paid)     AS paid_cnt,
               SUM(signed)   AS signed_cnt,
               SUM(handed)   AS handed_cnt
        FROM (
          SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym, 1 AS created, 0 AS approved, 0 AS paid, 0 AS signed, 0 AS handed
          FROM edoc_document WHERE created_at >= ? AND created_at <= ? + INTERVAL 1 DAY
          UNION ALL
          SELECT DATE_FORMAT(h.created_at,'%Y-%m') AS ym, 0, 1, 0, 0, 0
          FROM edoc_document_status h WHERE h.status='ISSUED' AND h.created_at >= ? AND h.created_at <= ? + INTERVAL 1 DAY
          UNION ALL
          SELECT DATE_FORMAT(p.paid_at,'%Y-%m') AS ym, 0, 0, 1, 0, 0
          FROM edoc_payment p WHERE p.status='PAID' AND p.paid_at >= ? AND p.paid_at <= ? + INTERVAL 1 DAY
          UNION ALL
          SELECT DATE_FORMAT(h.created_at,'%Y-%m') AS ym, 0, 0, 0, 1, 0
          FROM edoc_document_status h WHERE h.status='SIGNED' AND h.created_at >= ? AND h.created_at <= ? + INTERVAL 1 DAY
          UNION ALL
          SELECT DATE_FORMAT(h.created_at,'%Y-%m') AS ym, 0, 0, 0, 0, 1
          FROM edoc_document_status h WHERE h.status='HANDOVER' AND h.created_at >= ? AND h.created_at <= ? + INTERVAL 1 DAY
        ) t
        GROUP BY ym ORDER BY ym DESC";
$st = $conn->prepare($sql);
$st->execute([$f_from,$f_to,$f_from,$f_to,$f_from,$f_to,$f_from,$f_to,$f_from,$f_to]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$tot = ['created_cnt'=>0,'approved_cnt'=>0,'paid_cnt'=>0,'signed_cnt'=>0,'handed_cnt'=>0];
foreach ($rows as $r) foreach ($tot as $k=>$v) $tot[$k] += (int)$r[$k];

require '../components/layout.php';
?>
<style>
.rpt-card { background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.07); padding:18px; }
.rpt-filter { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin-bottom:14px; }
.rpt-filter .f { display:flex; flex-direction:column; gap:3px; font-size:.82rem; color:#555; }
.rpt-filter input, .rpt-filter button { border:1px solid #ddd; border-radius:8px; padding:7px 12px; font-size:.9rem; outline:none; }
.rpt-filter input:focus { border-color:#044482; }
.rpt-total { display:flex; gap:14px; margin-bottom:14px; flex-wrap:wrap; }
.rpt-total .box { flex:1; min-width:130px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 16px; }
.rpt-total .box .num { font-size:1.25rem; font-weight:700; color:#044482; }
.rpt-total .box .lbl { font-size:.78rem; color:#64748b; }
.rpt-table { width:100%; border-collapse:collapse; font-size:.9rem; }
.rpt-table th { background:#f1f5f9; color:#334155; text-align:left; padding:9px 12px; border-bottom:2px solid #e2e8f0; white-space:nowrap; }
.rpt-table td { padding:8px 12px; border-bottom:1px solid #eef2f7; }
.rpt-table tr:hover td { background:#f8fafc; }
.rpt-print { border:1px solid #ddd; border-radius:8px; background:#fff; padding:7px 14px; font-size:.85rem; cursor:pointer; }
@media print { .rpt-filter, .rpt-print, .sidebar, .navbar, .footer { display:none !important; } .rpt-card { box-shadow:none; } }
</style>

<div class="container-fluid py-3">
  <h5 class="mb-1"><i class="fas fa-chart-bar text-primary"></i> ລາຍງານການບໍລິການເອກະສານ (Document Service Report)</h5>
  <div class="text-muted mb-3" style="font-size:.82rem">ຈາກ <?= htmlspecialchars($f_from) ?> ຫາ <?= htmlspecialchars($f_to) ?></div>

  <form class="rpt-filter" method="get" action="report_service.php">
    <div class="f"><label>ວັນທີເລີ່ມ (From)</label><input type="date" name="f_from" value="<?= htmlspecialchars($f_from) ?>"></div>
    <div class="f"><label>ວັນທີສິ້ນສຸດ (To)</label><input type="date" name="f_to" value="<?= htmlspecialchars($f_to) ?>"></div>
    <button type="submit" class="btn btn-primary" style="border:none"><i class="fas fa-filter"></i> ກັ່ນຕອງ</button>
    <button type="button" class="rpt-print" onclick="window.print()"><i class="fas fa-print"></i> ພິມ</button>
  </form>

  <div class="rpt-total">
    <div class="box"><div class="num"><?= number_format($tot['created_cnt']) ?></div><div class="lbl">ສ້າງໃໝ່ (Created)</div></div>
    <div class="box"><div class="num"><?= number_format($tot['approved_cnt']) ?></div><div class="lbl">ອະນຸມັດ (Approved)</div></div>
    <div class="box"><div class="num"><?= number_format($tot['paid_cnt']) ?></div><div class="lbl">ຊຳລະແລ້ວ (Paid)</div></div>
    <div class="box"><div class="num"><?= number_format($tot['signed_cnt']) ?></div><div class="lbl">ເຊັນແລ້ວ (Signed)</div></div>
    <div class="box"><div class="num"><?= number_format($tot['handed_cnt']) ?></div><div class="lbl">ສົ່ງມອບແລ້ວ (Handed over)</div></div>
  </div>

  <div class="rpt-card">
    <table class="rpt-table">
      <thead><tr>
        <th>ເດືອນ (Month)</th>
        <th class="text-end">ສ້າງໃໝ່ (Created)</th>
        <th class="text-end">ອະນຸມັດ (Approved)</th>
        <th class="text-end">ຊຳລະແລ້ວ (Paid)</th>
        <th class="text-end">ເຊັນແລ້ວ (Signed)</th>
        <th class="text-end">ສົ່ງມອບແລ້ວ (Handed over)</th>
      </tr></thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="text-center text-muted py-3">ບໍ່ມີຂໍ້ມູນໃນໄລຍະນີ້ (No data in this period)</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['ym']) ?></td>
            <td class="text-end"><?= number_format((int)$r['created_cnt']) ?></td>
            <td class="text-end"><?= number_format((int)$r['approved_cnt']) ?></td>
            <td class="text-end"><?= number_format((int)$r['paid_cnt']) ?></td>
            <td class="text-end"><?= number_format((int)$r['signed_cnt']) ?></td>
            <td class="text-end"><?= number_format((int)$r['handed_cnt']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
      <?php if ($rows): ?>
      <tfoot><tr style="font-weight:700;background:#f8fafc">
        <td>ລວມ (Total)</td>
        <td class="text-end"><?= number_format($tot['created_cnt']) ?></td>
        <td class="text-end"><?= number_format($tot['approved_cnt']) ?></td>
        <td class="text-end"><?= number_format($tot['paid_cnt']) ?></td>
        <td class="text-end"><?= number_format($tot['signed_cnt']) ?></td>
        <td class="text-end"><?= number_format($tot['handed_cnt']) ?></td>
      </tr></tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>
<?php require '../components/layout_footer.php'; ?>