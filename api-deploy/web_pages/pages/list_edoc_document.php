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

$sql = "SELECT d.*, di.name_lo AS item_name, di.doc_item_code, c.first_name, c.last_name,
               v.name_lo AS village_name, t.name_lo AS taseng_name,
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

$statuses = ['SUBMITTED','PENDING_APPROVAL','ISSUED','PENDING_PAYMENT','PAID','SIGNED','HANDOVER','DOWNLOADED','REJECTED','CANCELED'];

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
.st-SUBMITTED { background:#e0f2fe;color:#0369a1; } .st-PENDING_APPROVAL { background:#fef9c3;color:#a16207; }
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
    <a href="/edoc/index.php">ໜ້າຫຼັກ</a><span>/</span><a href="#">ບໍລິການເອກະສານ</a><span>/</span>ລາຍການຄຳຮ້ອງ (Requests)
  </div>

  <div class="apims-page-header d-flex justify-content-between align-items-end mb-3">
    <div>
      <h1 class="apims-page-title" style="font-weight: 700; font-size: 1.5rem; color: #03305c; margin: 0;">
        ບໍລິການເອກະສານ - ລາຍການຄຳຮ້ອງ (Document Requests)
      </h1>
      <div class="action-buttons-top mb-3 mt-3 d-flex gap-2">
        <a href="../index.php" class="btn btn-danger" title="ປິດ"><i class="fas fa-power-off"></i></a>
        <button class="btn btn-success" onclick="openAddDoc()"><i class="fas fa-plus"></i> ຄຳຮ້ອງໃໝ່ (ພົນລະເມືອງມາຂໍ)</button>
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
      <select id="statusFilter" class="form-select" onchange="filterStatus()">
        <option value="">ທຸກສະຖານະ</option>
        <?php foreach ($statuses as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter == $s ? 'selected' : '' ?>><?= $s ?></option>
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
            <th width="12%" class="text-center">ຈຳນວນ (ກີບ)</th>
            <th width="14%" class="text-center">ສະຖານະ</th>
            <th width="9%" class="text-center">ວັນທີ</th>
            <th width="8%" class="text-center">ຈັດການ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($docs as $i => $row): ?>
          <tr>
            <td class="text-center"><?= $i + 1 ?></td>
            <td class="text-center"><span class="prov-code-badge"><?= htmlspecialchars($row['doc_no']) ?></span></td>
            <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?><br><small class="text-muted"><?= htmlspecialchars($row['village_name'] ?? '-') ?></small></td>
            <td><?= htmlspecialchars($row['item_name']) ?> <small class="text-muted">[<?= htmlspecialchars($row['doc_item_code']) ?>]</small></td>
            <td class="text-center"><?= number_format($row['amount'], 2) ?></td>
            <td class="text-center"><span class="st-badge st-<?= $row['status'] ?>"><?= $row['status'] ?></span></td>
            <td class="text-center"><small><?= date('d/m/Y', strtotime($row['created_at'])) ?></small></td>
            <td class="text-center"><button class="btn btn-primary btn-sm" onclick="openReview(<?= $row['id'] ?>)"><i class="fas fa-tasks"></i> ຈັດການ</button></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($docs)): ?>
          <tr><td colspan="8" class="text-center py-5" style="color:#aaa;"><i class="fas fa-folder-open fa-2x mb-2 d-block"></i> ຍັງບໍ່ມີຄຳຮ້ອງ</td></tr>
          <?php endif; ?>
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

<script>
$(document).ready(function() {
    var table = $('#docTable').DataTable({ paging: true, pageLength: 20, ordering: false, searching: true, info: true, language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/lo.json' }, dom: 'tip' });
    $('#searchDoc').on('keyup', function() { table.search(this.value).draw(); });
});
function filterStatus() { var s = $('#statusFilter').val(); window.location.href = 'list_edoc_document.php' + (s ? '?status=' + s : ''); }

function openAddDoc() { $('#formDoc')[0].reset(); $('#doc_hidden_id').val(''); $('#doc_amount').val(''); $('#doc_fields_wrap').html(''); $('#modalDocTitle').html('ຄຳຮ້ອງໃໝ່'); new bootstrap.Modal(document.getElementById('modalFormDoc')).show(); }

function loadDocFields() {
    var di = $('#doc_item').val();
    if (!di) { $('#doc_fields_wrap').html(''); $('#doc_amount').val(''); return; }
    $.get('../ajax/search/get_edoc_docitem_fields.php', { doc_item_id: di }, function(res){
        try { let d = JSON.parse(res);
            if (d.status==='success'){
                let h='';
                d.data.fields.forEach(f=>{ h += `<div class="mb-2"><label class="form-label">${f.field_label}</label><input type="text" name="field_${f.id}" class="form-control"></div>`; });
                $('#doc_fields_wrap').html(h);
                $('#doc_amount').val(d.data.price);
            }
        } catch(e){}
    });
}

function openReview(id) {
    $.post('../ajax/search/get_edoc_document.php', {id:id}, function(res){
        try {
            let r = JSON.parse(res);
            if (r.status === 'success') {
                renderReview(r.data);
                new bootstrap.Modal(document.getElementById('modalReviewDoc')).show();
            } else {
                Swal.fire('ຜິດພາດ', r.message, 'error');
            }
        } catch(e) { Swal.fire('ຜິດພາດ','ເກີດຂໍ້ຜິດພາດ','error'); }
    });
}

function renderReview(d) {
    var doc = d.doc, form = d.form, hist = d.history, inv = d.invoice;
    var h = '';
    h += '<div class="row mb-2"><div class="col-6"><b>ເລກທີ່:</b> '+doc.doc_no+'</div><div class="col-6"><b>ສະຖານະ:</b> <span class="st-badge st-'+doc.status+'">'+doc.status+'</span></div></div>';
    h += '<div class="row mb-2"><div class="col-6"><b>ພົນລະເມືອງ:</b> '+doc.first_name+' '+doc.last_name+'</div><div class="col-6"><b>ເບີໂທ:</b> '+doc.phone+'</div></div>';
    h += '<div class="row mb-2"><div class="col-6"><b>ເອກະສານ:</b> '+doc.item_name+' ['+doc.doc_item_code+']</div><div class="col-6"><b>ຈຳນວນ:</b> '+Number(doc.amount).toLocaleString()+' ກີບ</div></div>';
    if (doc.village_name) h += '<div class="row mb-2"><div class="col-12"><b>ທີ່:</b> '+doc.village_name+' / '+doc.taseng_name+'</div></div>';
    if (form.length) { h += '<hr><h6>ຂໍ້ມູນໃນແບບຟອມ</h6>'; form.forEach(f=>{ h += '<div class="mb-1"><b>'+f.field_label+':</b> '+ (f.field_value||'-') +'</div>'; }); }
    h += '<hr><h6>ປະຫວັດສະຖານະ</h6><ul class="list-unstyled">';
    hist.forEach(s=>{ h += '<li><span class="st-badge st-'+s.status+'">'+s.status+'</span> <small class="text-muted">'+s.remark+'</small></li>'; });
    h += '</ul>';
    if (inv) h += '<div class="mb-2"><b>ໃບເກັບກ້ຽວ:</b> '+inv.inv_no+' ('+inv.status+')</div>';
    h += '<div class="mt-2"><label class="form-label">ໝາຍເຫດການດຳເນີນ</label><textarea id="rev_remark" class="form-control" rows="2"></textarea></div>';
    $('#reviewBody').html(h);

    // action buttons by status
    var f = '<input type="hidden" id="rev_id" value="'+doc.id+'">';
    if (doc.status==='SUBMITTED') {
        f += '<button class="btn btn-success me-2" onclick="doAction(\'approve\')"><i class="fas fa-check"></i> ຮັບຮອງ (ສົ່ງອະນຸມັດ)</button>';
        f += '<button class="btn btn-danger me-2" onclick="doAction(\'reject\')"><i class="fas fa-times"></i> ປະຕິເສດ</button>';
    } else if (doc.status==='PENDING_APPROVAL') {
        f += '<button class="btn btn-success me-2" onclick="doAction(\'approve\')"><i class="fas fa-check"></i> ອະນຸມັດ</button>';
        f += '<button class="btn btn-danger me-2" onclick="doAction(\'reject\')"><i class="fas fa-times"></i> ປະຕິເສດ</button>';
    } else if (doc.status==='ISSUED') {
        f += '<div class="me-2 d-inline-block"><select id="pay_method" class="form-select form-select-sm d-inline-block" style="width:auto"><option value="CASH">ເງິນສົດ</option><option value="QR">QR</option><option value="TRANSFER">ໂອນ</option></select></div>';
        f += '<button class="btn btn-primary me-2" onclick="doAction(\'pay\')"><i class="fas fa-money-bill"></i> ບັນທຶກການຊຳລະ</button>';
    } else if (doc.status==='PAID') {
        f += '<button class="btn btn-info me-2" onclick="doAction(\'issue\')"><i class="fas fa-hand-holding"></i> ອອກເອກະສານ/ສົ່ງມອບ</button>';
    } else {
        f += '<span class="text-muted">ສິ້ນສຸດຂັ້ນຕອນນີ້</span>';
    }
    f += '<button class="btn btn-outline-secondary" data-bs-dismiss="modal">ປິດ</button>';
    $('#reviewFooter').html(f);
}

function doAction(action) {
    var id = $('#rev_id').val();
    var remark = $('#rev_remark').val() || '';
    var extra = {};
    if (action==='pay') extra.method = $('#pay_method').val();
    $.post('../ajax/action/process_edoc_document.php', Object.assign({id:id, action:action, remark:remark}, extra), function(res){
        try { let r = JSON.parse(res);
            if (r.status==='success'){ Swal.fire({title:'ສຳເລັດ!', text:r.message, icon:'success', timer:1500, showConfirmButton:false}).then(()=>location.reload()); }
            else Swal.fire('ຜິດພາດ', r.message, 'error');
        } catch(e){ Swal.fire('ຜິດພາດ','ເກີດຂໍ້ຜິດພາດ','error'); }
    });
}

function deleteEdocDocument(id, no) {
    Swal.fire({ title:'ຕ້ອງການລຶບຄຳຮ້ອງນີ້?', text:no, icon:'warning', showCancelButton:true, confirmButtonColor:'#d33', cancelButtonColor:'#044482', confirmButtonText:'ລຶບ!', cancelButtonText:'ຍົກເລີກ' }).then((r)=>{
        if (r.isConfirmed){ $.post('../ajax/del/delete_edoc_document.php',{id:id},function(res){ try{ let d=JSON.parse(res); if(d.status==='success') location.reload(); else Swal.fire('ຜິດພາດ',d.message,'error'); }catch(e){} }); }
    });
}

<?php if ($alert): ?>
Swal.fire({ icon: '<?= $alert['type'] ?>', title: '<?= $alert['type'] === 'success' ? 'ສຳເລັດ!' : 'ແຈ້ງເຕືອນ!' ?>', text: '<?= addslashes($alert['message']) ?>', confirmButtonColor: '#044482', confirmButtonText: 'ຕົກລົງ' });
<?php endif; ?>
</script>