<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require '../connect.php';

// Reports are visible to every logged-in role (team decision 2026-08-22)
$_current = 'report_revenue.php';
$alert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);

// ── filters: date range (default: current year) ─────────────────────
$f_from = $_GET['f_from'] ?? date('Y-01-01');
$f_to   = $_GET['f_to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_from)) $f_from = date('Y-01-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_to))   $f_to   = date('Y-m-d');

// ── revenue by month + doc type ──────────────────────────────────────
// A paid doc's real amount: edoc_payment_evidence.amount (verified) preferred,
// else edoc_payment.amount. Fee/doc amount 0 fallbacks handled via NULLIF.
$sql = "SELECT DATE_FORMAT(p.paid_at, '%Y-%m') AS ym,
               COALESCE(dt.name_lo, '—') AS type_name,
               COUNT(DISTINCT d.id) AS doc_count,
               COALESCE(SUM(COALESCE(ev.amount, NULLIF(p.amount,0), 0)), 0) AS collected
        FROM edoc_payment p
        JOIN edoc_invoice i ON p.invoice_id = i.id
        JOIN edoc_document d ON i.doc_id = d.id
        LEFT JOIN edoc_doc_item di ON d.doc_item_id = di.id
        LEFT JOIN edoc_doc_type dt ON di.doc_type_id = dt.id
        LEFT JOIN (SELECT pe.doc_id, MAX(pe.amount) AS amount
                   FROM edoc_payment_evidence pe WHERE pe.verified = 1 GROUP BY pe.doc_id) ev
               ON ev.doc_id = d.id
        WHERE p.status = 'PAID' AND p.paid_at IS NOT NULL
          AND p.paid_at >= ? AND p.paid_at <= ? + INTERVAL 1 DAY
        GROUP BY ym, dt.id, dt.name_lo
        ORDER BY ym DESC, dt.name_lo ASC";
$st = $conn->prepare($sql);
$st->execute([$f_from, $f_to]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$grandCount = 0; $grandAmt = 0.0;
foreach ($rows as $r) { $grandCount += (int)$r['doc_count']; $grandAmt += (float)$r['collected']; }

require '../components/layout.php';
?>
<style>
.rpt-card { background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.07); padding:18px; }
.rpt-filter { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin-bottom:14px; }
.rpt-filter .f { display:flex; flex-direction:column; gap:3px; font-size:.82rem; color:#555; }
.rpt-filter input, .rpt-filter button { border:1px solid #ddd; border-radius:8px; padding:7px 12px; font-size:.9rem; outline:none; }
.rpt-filter input:focus { border-color:#044482; }
.rpt-total { display:flex; gap:18px; margin-bottom:14px; flex-wrap:wrap; }
.rpt-total .box { flex:1; min-width:150px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 16px; }
.rpt-total .box .num { font-size:1.35rem; font-weight:700; color:#044482; }
.rpt-total .box .lbl { font-size:.8rem; color:#64748b; }
.rpt-table { width:100%; border-collapse:collapse; font-size:.9rem; }
.rpt-table th { background:#f1f5f9; color:#334155; text-align:left; padding:9px 12px; border-bottom:2px solid #e2e8f0; white-space:nowrap; }
.rpt-table td { padding:8px 12px; border-bottom:1px solid #eef2f7; }
.rpt-table tr:hover td { background:#f8fafc; }
.rpt-print { border:1px solid #ddd; border-radius:8px; background:#fff; padding:7px 14px; font-size:.85rem; cursor:pointer; }
@media print { .rpt-filter, .rpt-print, .sidebar, .navbar, .footer { display:none !important; } .rpt-card { box-shadow:none; } }
</style>

<div class="container-fluid py-3">
  <h5 class="mb-1"><i class="fas fa-coins text-success"></i> ລາຍງານ ການເກັບລາຍຮັບ (Revenue Collection Report)</h5>
  <div class="text-muted mb-3" style="font-size:.82rem">ຈາກ <?= htmlspecialchars($f_from) ?> ຫາ <?= htmlspecialchars($f_to) ?></div>

  <form class="rpt-filter" method="get" action="report_revenue.php">
    <div class="f"><label>ວັນທີເລີ່ມ (From)</label><input type="date" name="f_from" value="<?= htmlspecialchars($f_from) ?>"></div>
    <div class="f"><label>ວັນທີສິ້ນສຸດ (To)</label><input type="date" name="f_to" value="<?= htmlspecialchars($f_to) ?>"></div>
    <button type="submit" class="btn btn-primary" style="border:none"><i class="fas fa-filter"></i> ກັ່ນຕອງ</button>
    <button type="button" class="rpt-print" onclick="window.print()"><i class="fas fa-print"></i> ພິມ</button>
  </form>

  <div class="rpt-total">
    <div class="box"><div class="num"><?= number_format($grandCount) ?></div><div class="lbl">ຈຳນວນເອກະສານທີ່ຊຳລະແລ້ວ (Paid docs)</div></div>
    <div class="box"><div class="num"><?= number_format($grandAmt, 2) ?></div><div class="lbl">ລາຍຮັບທັງໝົດ ກີບ (Total collected)</div></div>
  </div>

  <div class="rpt-card">
    <table class="rpt-table" id="rptTable">
      <thead><tr><th>ເດືອນ (Month)</th><th>ປະເພດເອກະສານ (Doc type)</th><th class="text-end">ຈຳນວນ (Count)</th><th class="text-end">ລາຍຮັບ ກີບ (Collected)</th></tr></thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="4" class="text-center text-muted py-3">ບໍ່ມີຂໍ້ມູນໃນໄລຍະນີ້ (No data in this period)</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['ym']) ?></td>
            <td><?= htmlspecialchars($r['type_name']) ?></td>
            <td class="text-end"><?= number_format((int)$r['doc_count']) ?></td>
            <td class="text-end"><?= number_format((float)$r['collected'], 2) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
      <?php if ($rows): ?>
      <tfoot><tr style="font-weight:700;background:#f8fafc">
        <td colspan="2">ລວມ (Total)</td>
        <td class="text-end"><?= number_format($grandCount) ?></td>
        <td class="text-end"><?= number_format($grandAmt, 2) ?></td>
      </tr></tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>