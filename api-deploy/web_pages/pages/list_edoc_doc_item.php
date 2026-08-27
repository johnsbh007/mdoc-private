<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require '../connect.php';
require_once '../edoc_auth.php';
// Basic-data lists are viewable by all roles; edit/delete require can_basic_data (buttons wrapped below)

$_current = 'list_edoc_doc_item.php';
$alert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);

$stmtTypes = $conn->query("SELECT id, doc_type_code, name_lo FROM edoc_doc_type ORDER BY doc_type_code ASC");
$docTypes = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);

$typeFilter = $_GET['doc_type_id'] ?? '';
$where = '';
$params = [];
if ($typeFilter) { $where = "WHERE di.doc_type_id = ?"; $params[] = $typeFilter; }

$sql = "SELECT di.*, dt.doc_type_code, dt.name_lo AS type_name
        FROM edoc_doc_item di
        JOIN edoc_doc_type dt ON di.doc_type_id = dt.id
        $where
        ORDER BY dt.doc_type_code ASC, di.doc_item_code ASC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($items);

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
#docItemTable { width: 100% !important; border-collapse: collapse; margin: 0 !important; }
#docItemTable thead tr th { background-color: #044482 !important; color: white !important; font-weight: 600; font-size: 0.88rem; padding: 12px 16px; border: none !important; }
#docItemTable tbody tr { border-bottom: 1px solid #f5f5f5; transition: background 0.15s; }
#docItemTable tbody tr:hover { background-color: #e8f0fb; }
#docItemTable tbody td { padding: 12px 16px; font-size: 0.9rem; color: #333; border: none !important; vertical-align: middle; }
.code-badge { background-color: #e6f0fa; color: #044482; border-radius: 6px; padding: 3px 11px; font-size: 0.82rem; font-weight: 700; display: inline-block; min-width: 44px; text-align: center; }
.dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_length { display: none !important; }
.dataTables_wrapper .dataTables_info { padding: 10px 20px; font-size: 0.82rem; color: #888; }
.dataTables_wrapper .dataTables_paginate { padding: 8px 20px; }
.dataTables_wrapper .paginate_button.current { background: #044482 !important; color: white !important; border: none !important; }
</style>

<div class="container-fluid py-4">
  <div class="prov-breadcrumb">
    <a href="/edoc/index.php">ໜ້າຫຼັກ</a><span>/</span><a href="#">ຂໍ້ມູນພື້ນຖານ</a><span>/</span>
    ລາຍການເອກະສານ
  </div>

  <div class="apims-page-header d-flex justify-content-between align-items-end mb-3">
    <div>
      <h1 class="apims-page-title" style="font-weight: 700; font-size: 1.5rem; color: #03305c; margin: 0;">
        ລາຍການເອກະສານ (Document Items) - eDoc
      </h1>
      <div class="action-buttons-top mb-3 mt-3 d-flex gap-2">
        <a href="../index.php" class="btn btn-danger" title="ປິດ"><i class="fas fa-power-off"></i></a>
        <?php if (edoc_can_basic_data()): ?><button class="btn btn-success" onclick="openAddDocItem()"><i class="fas fa-plus"></i> ເພີ່ມໃໝ່</button><?php endif; ?>
        <button class="btn btn-light border" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Refresh</button>
      </div>
    </div>
    <div class="prov-search-wrap mt-1">
      <i class="fas fa-search"></i>
      <input type="text" id="searchDocItem" placeholder="ຄົ້ນຫາ...">
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-md-4">
      <select id="typeFilter" class="form-select" onchange="filterByType()">
        <option value="">ທຸກປະເພດ</option>
        <?php foreach ($docTypes as $t): ?>
          <option value="<?= $t['id'] ?>" <?= $typeFilter == $t['id'] ? 'selected' : '' ?>>
            [<?= htmlspecialchars($t['doc_type_code']) ?>] <?= htmlspecialchars($t['name_lo']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="prov-table-card">
    <div style="overflow-y: auto; max-height: calc(100vh - 360px);">
      <table id="docItemTable" class="table mb-0">
        <thead>
          <tr>
            <th width="5%" class="text-center">#</th>
            <th width="14%" class="text-center">ປະເພດ</th>
            <th width="12%" class="text-center">ລະຫັດ</th>
            <th>ຊື່ (ພາສາລາວ)</th>
            <th>ຊື່ (ພາສາອັງກິດ)</th>
            <th width="9%" class="text-center">ຄ່າທຳນຽມ</th>
            <th width="8%" class="text-center">ສະຖານະ</th>
            <th width="5%" class="text-center">ແກ້ໄຂ</th>
            <th width="5%" class="text-center">ລຶບ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $i => $row): ?>
          <tr>
            <td class="text-center"><?= $i + 1 ?></td>
            <td class="text-center"><?= htmlspecialchars($row['type_name']) ?></td>
            <td class="text-center"><span class="code-badge"><?= htmlspecialchars($row['doc_item_code']) ?></span></td>
            <td><?= htmlspecialchars($row['name_lo']) ?></td>
            <td><?= htmlspecialchars($row['name_en'] ?? '-') ?></td>
            <td class="text-center"><?= number_format($row['price'], 2) ?></td>
            <td class="text-center"><?= $row['is_active'] ? '<span class="badge bg-success">ເປີດ</span>' : '<span class="badge bg-secondary">ປິດ</span>' ?></td>
            <td class="text-center"><?php if (edoc_can_basic_data()): ?><button class="btn btn-warning" onclick="openEditDocItem(<?= $row['id'] ?>)"><i class="fas fa-edit"></i></button><?php endif; ?></td>
            <td class="text-center"><?php if (edoc_can_basic_data()): ?><button class="btn btn-danger" onclick="deleteDocItem(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name_lo']) ?>')"><i class="fas fa-trash"></i></button><?php endif; ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($items)): ?>
          <tr><td colspan="9" class="text-center py-5" style="color:#aaa;"><i class="fas fa-folder-open fa-2x mb-2 d-block"></i> ຍັງບໍ່ມີຂໍ້ມູນ</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="p-3 bg-light border-top" style="border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
      <div class="prov-count">ທັງໝົດ <strong><?= $total ?></strong> ລາຍການ</div>
    </div>
  </div>
</div>

<?php
require '../modal/modal_form_edoc_doc_item.php';
require '../components/layout_footer.php';
?>

<script>
$(document).ready(function() {
    var table = $('#docItemTable').DataTable({ paging: true, pageLength: 20, ordering: false, searching: true, info: true, language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/lo.json' }, dom: 'tip' });
    $('#searchDocItem').on('keyup', function() { table.search(this.value).draw(); });
});

function filterByType() {
    var t = $('#typeFilter').val();
    window.location.href = 'list_edoc_doc_item.php' + (t ? '?doc_type_id=' + t : '');
}

function openAddDocItem() {
    $('#formDocItem')[0].reset();
    $('#di_hidden_id').val('');
    $('#modalDocItemTitle').html('ເພີ່ມລາຍການເອກະສານໃໝ່');
    var t = $('#typeFilter').val();
    if (t) $('#doc_type_id').val(t);
    new bootstrap.Modal(document.getElementById('modalFormDocItem')).show();
}

function openEditDocItem(id) {
    $.ajax({ url: '../ajax/search/get_edoc_doc_item.php', type: 'POST', data: { id: id }, dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                let d = res.data;
                $('#formDocItem')[0].reset();
                $('#di_hidden_id').val(d.id);
                $('#doc_type_id').val(d.doc_type_id);
                $('#doc_item_code').val(d.doc_item_code);
                $('#name_lo').val(d.name_lo);
                $('#name_en').val(d.name_en);
                $('#unit').val(d.unit);
                $('#price').val(d.price);
                $('#is_active').val(d.is_active);
                $('#modalDocItemTitle').html('ແກ້ໄຂລາຍການເອກະສານ');
                new bootstrap.Modal(document.getElementById('modalFormDocItem')).show();
            } else { Swal.fire('ຜິດພາດ', res.message, 'error'); }
        }, error: function() { Swal.fire('ຜິດພາດ', 'ດຶງຂໍ້ມູນບໍ່ໄດ້!', 'error'); }
    });
}

function deleteDocItem(id, name) {
    Swal.fire({ title: 'ຕ້ອງການລຶບຂໍ້ມູນແທ້ບໍໂ?', text: name, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#044482', confirmButtonText: 'ຕົກລົງລຶບ!', cancelButtonText: 'ຍົກເລີກ' }).then((r) => {
        if (r.isConfirmed) {
            $.post('../ajax/del/delete_edoc_doc_item.php', { id: id }, function(res) {
                try { let data = JSON.parse(res);
                    if (data.status === 'success') Swal.fire({title: 'ລຶບແລ້ວ!', text: data.message, icon: 'success', timer: 1500, showConfirmButton: false}).then(() => location.reload());
                    else Swal.fire('ຜິດພາດ', data.message, 'error');
                } catch(e) { Swal.fire('ຜິດພາດ', 'ເກີດຂໍ້ຜິດພາດ', 'error'); }
            });
        }
    });
}

<?php if ($alert): ?>
Swal.fire({ icon: '<?= $alert['type'] ?>', title: '<?= $alert['type'] === 'success' ? 'ສຳເລັດ!' : 'ແຈ້ງເຕືອນ!' ?>', text: '<?= addslashes($alert['message']) ?>', confirmButtonColor: '#044482', confirmButtonText: 'ຕົກລົງ' });
<?php endif; ?>
</script>