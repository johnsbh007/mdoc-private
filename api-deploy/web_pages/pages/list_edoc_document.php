<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require '../connect.php';

$_current = 'list_edoc_document.php';
$alert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);

$statusFilter = $_GET['status'] ?? '';
$where = '';
$params = [];
if ($statusFilter) { $where = "WHERE d.status = ?"; $params[] = $statusFilter; }

$sql = "SELECT d.*, di.name_lo AS item_name, di.doc_item_code, di.doc_type_id, c.first_name, c.last_name,
               v.name_lo AS village_name, t.name_lo AS taseng_name,
               COALESCE(NULLIF(d.amount,0), di.price) AS eff_amount,
               (SELECT status FROM edoc_document_status WHERE doc_id = d.id ORDER BY id DESC LIMIT 1) AS last_status
        FROM edoc_document d
        JOIN edoc_doc_item di ON d.doc_item_id = di.id
        JOIN edoc_citizen c ON d.citizen_id = c.id
        LEFT JOIN edoc_village v ON d.village_id = v.id
        LEFT JOIN edoc_taseng t ON v.tasseng_id = t.id
        $where
        ORDER BY d.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($docs);
// Show a friendly popup when a status filter is active but returned no records
$showEmptyStatusAlert = ($statusFilter !== '' && empty($docs));

// Status code -> display label (Lao / English). Order: draft, then the rest.
// NOTE: SUBMITTED was merged into PENDING_APPROVAL (2026-08-22) — kept in the map only
// so legacy rows/badges still render a Lao label; it is excluded from the filter dropdown.
$status_labels = [
  'DRAFT'            => 'ຮ່າງ/draft',
  'SUBMITTED'        => 'ຮັບແລ້ວ/submitted (legacy)',
  'PENDING_APPROVAL' => 'ລໍຖ້າອະນຸມັດ/Pending Approval',
  'NEEDS_REVISION'   => 'ຕ້ອງການປັບປຸງ/Needs Revision',
  'ISSUED'           => 'ອະນຸມັດແລ້ວ/Issued',
  'PENDING_PAYMENT'  => 'ລໍຖ້າຊໍາລະ/Pending Payment',
  'PAID'             => 'ຊໍາລະແລ້ວ/Paid',
  'SIGNED'           => 'ເຊັນແລ້ວ/Signed',
  'HANDOVER'         => 'ສົ່ງມອບແລ້ວ/Handover',
  'DOWNLOADED'       => 'ດາວໂຫລດແລ້ວ/Downloaded',
  'REJECTED'         => 'ປະຕິເສດ/Rejected',
  'CANCELED'         => 'ຍົກເລີກ/Canceled',
];

require '../components/layout.php';
?>

<style>
.prov-breadcrumb { font-size: 0.85rem; color: #666; margin-bottom: 10px; }
.prov-breadcrumb a { color: #044482; text-decoration: none; }
.prov-breadcrumb a:hover { text-decoration: underline; }
.prov-breadcrumb span { color: #bbb; margin: 0 6px; }
.prov-table-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); overflow: hidden; }
.prov-count { font-size: 0.9rem; color: #555; font-weight: 500; }
.prov-search-wrap { position: relative; }
.prov-search-wrap input { border: 1px solid #ddd; border-radius: 8px; padding: 7px 14px 7px 36px; font-size: 0.9rem; width: 230px; outline: none; transition: border 0.2s; }
.prov-search-wrap input:focus { border-color: #044482; }
.prov-search-wrap i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 0.85rem; }
#docTable { width: 100% !important; border-collapse: collapse; margin: 0 !important; }
#docTable thead tr th { background-color: #044482 !important; color: white !important; font-weight: 600; font-size: 0.85rem; padding: 11px 14px; border: none !important; position: sticky; top: 0; z-index: 5; }
#docTable tbody tr { border-bottom: 1px solid #f5f5f5; transition: background 0.15s; }
#docTable tbody tr:hover { background-color: #e8f0fb; }
#docTable tbody td { padding: 11px 14px; font-size: 0.88rem; color: #333; border: none !important; vertical-align: middle; }
.st-badge { font-size: 0.74rem; font-weight: 700; padding: 3px 9px; border-radius: 20px; white-space: nowrap; }
.src-badge { font-size: 0.72rem; font-weight: 600; padding: 3px 8px; border-radius: 20px; white-space: nowrap; }
.src-office { background:#e0e7ff; color:#3730a3; }
.src-mobile { background:#fce7f3; color:#9d174d; }
.st-SUBMITTED { background:#e0f2fe;color:#0369a1; } .st-DRAFT { background:#fde68a;color:#a16207; } .st-PENDING_APPROVAL { background:#fef9c3;color:#a16207; }
.st-ISSUED { background:#ede9fe;color:#6d28d9; } .st-PENDING_PAYMENT { background:#ffedd5;color:#c2410c; }
.st-PAID { background:#dcfce7;color:#15803d; } .st-SIGNED { background:#dbeafe;color:#1d4ed8; }
.st-HANDOVER { background:#cffafe;color:#0e7490; } .st-DOWNLOADED { background:#bbf7d0;color:#166534; }
.st-REJECTED { background:#fee2e2;color:#b91c1c; } .st-CANCELED { background:#f1f5f9;color:#475569; }
.dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_length { display: none !important; }
.dataTables_wrapper .dataTables_info { padding: 10px 20px; font-size: 0.82rem; color: #888; }
.dataTables_wrapper .dataTables_paginate { padding: 8px 20px; }
.dataTables_wrapper .paginate_button.current { background: #044482 !important; color: white !important; border: none !important; }
</style>

<div class="container-fluid py-4">
  <div class="prov-breadcrumb">
    <a href="/edoc/index.php">ໜ້າຫຼັກ</a><span>/</span><a href="#">ບໍລິການເອກະສານ</a><span>/</span>ລາຍການຄຳຮ້ອງ (Requests)</div>

  <div class="apims-page-header d-flex justify-content-between align-items-end mb-3">
    <div>
      <h1 class="apims-page-title" style="font-weight: 700; font-size: 1.5rem; color: #03305c; margin: 0;">
        ບໍລິການເອກະສານ - ລາຍການຄຳຮ້ອງ (Document Requests)
      </h1>
      <div class="action-buttons-top mb-3 mt-3 d-flex gap-2">
        <a href="../index.php" class="btn btn-danger" title="ປິດ"><i class="fas fa-power-off"></i></a>
        <button class="btn btn-primary" onclick="location.href='select_doc_type.php'"><i class="fas fa-file-alt"></i> ຄຳຮ້ອງໃໝ່ (ຮ່າງໂດຍພະນັກງານ)</button>
        <?php /* walk-in direct-submit button hidden for now (team decision 2026-08-22) — officer drafts go via New Request; mobile submissions arrive via mDoc app
        <button class="btn btn-success" onclick="location.href='select_doc_type.php?mode=submit'"><i class="fas fa-plus"></i> ຄຳຮ້ອງໃໝ່ (ພົນລະເມືອງຂໍມາ)</button>
        */ ?>
        <button class="btn btn-light border" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Refresh</button>
      </div>
    </div>
    <div class="prov-search-wrap mt-1">
      <i class="fas fa-search"></i>
      <input type="text" id="searchDoc" placeholder="ຄົ້ນຫາເລກທີ່/ຊື່...">
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-auto">
      <select id="statusFilter" class="form-select" style="min-width: 230px; width: auto; max-width: 320px;" onchange="filterStatus()">
        <option value="">ທຸກສະຖານະ</option>
        <?php foreach ($status_labels as $code => $label): ?>
          <?php if ($code === 'SUBMITTED') continue; // merged into PENDING_APPROVAL ?>
          <option value="<?= $code ?>" <?= $statusFilter == $code ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="prov-table-card">
    <div style="overflow-y: auto; max-height: calc(100vh - 340px);">
      <table id="docTable" class="table mb-0">
        <thead>
          <tr>
            <th width="5%" class="text-center">#</th>
            <th width="12%" class="text-center">ເລກທີ່</th>
            <th>ພົນລະເມືອງ</th>
            <th>ເອກະສານ</th>
            <th width="8%" class="text-center">ແຫຼ່ງທີ່ມາ</th>
            <th width="11%" class="text-center">ຈຳນວນ (ກີບ)</th>
            <th width="13%" class="text-center">ສະຖານະ</th>
            <th width="9%" class="text-center">ວັນທີ</th>
            <th width="11%" class="text-center">ຈັດການ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($docs as $i => $row): ?>
          <tr id="doc-row-<?= $row['id'] ?>">
            <td class="text-center"><?= $i + 1 ?></td>
            <td class="text-center"><span class="prov-code-badge"><?= htmlspecialchars($row['doc_no']) ?></span></td>
            <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?><br><small class="text-muted"><?= htmlspecialchars($row['village_name'] ?? '-') ?></small></td>
            <td><?= htmlspecialchars($row['item_name']) ?> <small class="text-muted">[<?= htmlspecialchars($row['doc_item_code']) ?>]</small></td>
            <td class="text-center">
              <?php if (($row['source'] ?? 'OFFICE') === 'MOBILE'): ?>
                <span class="src-badge src-mobile" title="ຄຳຮ້ອງຈາກມືຖື (mDoc)"><i class="fas fa-mobile-alt"></i> ມືຖື</span>
              <?php else: ?>
                <span class="src-badge src-office" title="ຄຳຮ້ອງຈາກສຳນັກງານ"><i class="fas fa-building"></i> ສຳນັກງານ</span>
              <?php endif; ?>
            </td>
            <td class="text-center"><?= number_format((float)($row['eff_amount'] ?: 0), 2) ?></td>
            <td class="text-center"><span class="st-badge st-<?= $row['status'] ?>"><?= $status_labels[$row['status']] ?? $row['status'] ?></span></td>
            <td class="text-center"><small><?= date('d/m/Y', strtotime($row['created_at'])) ?></small></td>
            <td class="text-center action-cell">
              <?php if (in_array($row['status'], ['DRAFT','NEEDS_REVISION'], true)): ?>
                <button class="btn btn-warning btn-sm" onclick="location.href='request_doc.php?doc_id=<?= $row['id'] ?>&mode=draft'"><i class="fas fa-edit"></i> <?= $row['status'] === 'NEEDS_REVISION' ? 'ປັບປຸງ' : 'ແກ້ໄຂ' ?></button>
              <?php elseif (in_array($row['status'], ['PENDING_APPROVAL','ISSUED','PENDING_PAYMENT','PAID','SIGNED'], true)): ?>
                <a class="btn btn-primary btn-sm" href="review_edoc_document.php?doc_id=<?= $row['id'] ?>"><i class="fas fa-tasks"></i> ຈັດການ</a>
              <?php endif; ?>
              <?php if ($row['doc_type_id'] == 1 && in_array($row['status'], ['PAID','SIGNED','HANDOVER','DOWNLOADED'], true)): ?>
                <a class="btn btn-outline-success btn-sm" target="_blank" href="../generate/generate_edoc_document.php?doc_id=<?= $row['id'] ?>"><i class="fas fa-file-word"></i> ໃບຢັ້ງຢືນ</a>
              <?php endif; ?>
              <?php // TODO(roles): gate this delete button with a user-capability check (not everyone may delete) ?>
              <button type="button" class="btn btn-danger btn-sm ms-1" onclick="confirmDeleteDoc(<?= (int)$row['id'] ?>, <?= htmlspecialchars(json_encode($row['doc_no']), ENT_QUOTES, 'UTF-8') ?>)"><i class="fas fa-trash"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="p-3 bg-light border-top" style="border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
      <div class="prov-count">ທັງໝົດ <strong><?= $total ?></strong> ຄຳຮ້ອງ</div>
    </div>
  </div>
</div>

<?php
require '../modal/modal_form_edoc_document.php';
require '../modal/modal_review_edoc_document.php';
require '../components/layout_footer.php';
?>
<!-- DataTables (required for search/pagination) -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#docTable').DataTable({ paging: true, pageLength: 20, ordering: false, searching: true, info: true, language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/lo.json' }, dom: 'tip' });
    $('#searchDoc').on('keyup', function() { table.search(this.value).draw(); });
    <?php if ($showEmptyStatusAlert): ?>
    Swal.fire({
      icon: 'info',
      title: 'ບໍ່ມີເອກະສານສະຖານະນີ້',
      text: 'ບໍ່ມີຄຳຮ້ອງໃນສະຖານະ <?= htmlspecialchars($status_labels[$statusFilter] ?? $statusFilter, ENT_QUOTES, 'UTF-8') ?>',
      confirmButtonColor: '#044482',
      confirmButtonText: 'ຕົກລົງ'
    });
    <?php endif; ?>
});
function filterStatus() { var s = $('#statusFilter').val(); window.location.href = 'list_edoc_document.php' + (s ? '?status=' + s : ''); }

function clearDocForm() {
  $('#formDoc')[0].reset();
  $('#doc_hidden_id').val('');
  $('#doc_citizen').val('');
  $('#doc_item').val('');
  $('#doc_village').val('');
  $('#manual_first_name').val('');
  $('#manual_last_name').val('');
  $('#manual_gender').val('');
  $('#manual_dob').val('');
  $('#manual_phone').val('');
  $('#manual_email').val('');
  $('#manual_id_card').val('');
  $('#manual_address_line').val('');
  $('#doc_amount').val('');
  $('#doc_remark').val('');
  $('#doc_fields_wrap').html('');
}

function openAddDoc() {
  clearDocForm();
  $('#doc_mode').val('submit');
  $('#modalDocTitle').html('ຄຳຮ້ອງໃໝ່');
  $('#doc_status_hint').text('PENDING_APPROVAL');
  $('#submitDocBtn').text('ບັນທຶກຄຳຮ້ອງ');
  new bootstrap.Modal(document.getElementById('modalFormDoc')).show();
}

function openDraftModal() {
  clearDocForm();
  $('#doc_mode').val('draft');
  $('#modalDocTitle').html('ບັນທຶກເປັນຮ່າງ (DRAFT)');
  $('#doc_status_hint').text('DRAFT');
  $('#submitDocBtn').text('ບັນທຶກເປັນຮ່າງ');
  new bootstrap.Modal(document.getElementById('modalFormDoc')).show();
}

function parseJson(res) {
  if (typeof res === 'object' && res !== null) return res;
  try { return JSON.parse(res); } catch(e) { return null; }
}

function loadDocFields() {
  var di = $('#doc_item').val();
  if (!di) { $('#doc_fields_wrap').html(''); $('#doc_amount').val(''); return; }
  $.get('../ajax/search/get_edoc_docitem_fields.php', { doc_item_id: di }, function(res){
    try {
      let d = parseJson(res);
      if (d.status==='success') {
        let h='';
        d.data.fields.forEach(f => { h += `<div class="mb-2"><label class="form-label">${f.field_label}</label><input type="text" name="field_${f.id}" class="form-control"></div>`; });
        $('#doc_fields_wrap').html(h);
        $('#doc_amount').val(d.data.price);
      }
    } catch(e) {}
  });
}

function loadDraftToModal(d, formRows) {
  clearDocForm();
  $('#doc_hidden_id').val(d.id || '');
  $('#doc_mode').val('draft');
  $('#doc_citizen').val(d.citizen_id || '');
  $('#doc_item').val(d.doc_item_id || '');
  $('#doc_village').val(d.village_id || '');
  $('#manual_first_name').val(d.first_name || '');
  $('#manual_last_name').val(d.last_name || '');
  $('#manual_gender').val(d.gender || '');
  $('#manual_dob').val(d.dob || '');
  $('#manual_phone').val(d.phone || '');
  $('#manual_email').val(d.email || '');
  $('#manual_id_card').val(d.id_card || '');
  $('#manual_address_line').val(d.address_line || '');
  $('#doc_amount').val(d.amount || '0.00');
  $('#doc_remark').val(d.latest_remark || '');
  $('#modalDocTitle').html('ແກ້ໄຂຮ່າງ');
  $('#doc_status_hint').text('DRAFT');
  $('#submitDocBtn').text('ບັນທຶກເປັນຮ່າງ');
  loadDocFields();
  if (formRows && formRows.length) {
    setTimeout(function(){
      formRows.forEach(function(f){ $('#doc_fields_wrap [name="field_' + f.field_id + '"]').val(f.field_value || ''); });
    }, 350);
  }
  new bootstrap.Modal(document.getElementById('modalFormDoc')).show();
}

function openDraftEntry(id) {
  $.post('../ajax/search/get_edoc_document.php', {id:id}, function(res){
    try {
      let r = parseJson(res);
      if (!r) { Swal.fire('ຜິດພາດ','ເກີດຂໍ້ຜິດພາດ','error'); return; }
      if (r.status === 'success') {
        loadDraftToModal(r.data.doc, r.data.form);
      } else {
        Swal.fire('ຜິດພາດ', r.message, 'error');
      }
    } catch(e) {
      Swal.fire('ຜິດພາດ','ເກີດຂໍ້ຜິດພາດ','error');
    }
  });
}

function submitDraftFromModal() { $('#doc_mode').val('draft'); $('#formDoc').submit(); }

function confirmDeleteDoc(id, docNo) {
  Swal.fire({
    title: 'ຢືນຢັນການລຶບ?',
    html: 'ທ່ານຕ້ອງການລຶບຄຳຮ້ອງ <strong>' + (docNo || ('#' + id)) + '</strong> ບໍ່?<br>ການກະທຳນີ້ບໍ່ສາມາດຍົກເລີກໄດ້.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'ລຶບ',
    cancelButtonText: 'ຍົກເລີກ'
  }).then(function(result) {
    if (!result.isConfirmed) return;
    $.post('../ajax/del/delete_edoc_document.php', {id: id}, function(res) {
      var r = parseJson(res);
      if (r && r.status === 'success') {
        Swal.fire('ລຶບສຳເລັດ', r.message || 'ລຶບຄຳຮ້ອງສຳເລັດ', 'success');
        var row = document.getElementById('doc-row-' + id);
        if (row && window.$ && $.fn.DataTable) {
          var dt = $('#docTable').DataTable();
          dt.row(row).remove().draw(false);
        } else if (row) {
          row.parentNode.removeChild(row);
        }
        var cnt = document.querySelector('.prov-count strong');
        if (cnt) { var n = parseInt(cnt.textContent, 10); if (!isNaN(n) && n > 0) cnt.textContent = n - 1; }
      } else {
        Swal.fire('ຜິດພາດ', (r && r.message) ? r.message : 'ເກີດຂໍ້ຜິດພາດ', 'error');
      }
    }).fail(function() {
      Swal.fire('ຜິດພາດ', 'ບໍ່ສາມາດເຊື່ອມຕໍ່ໄດ້', 'error');
    });
  });
}

</script>