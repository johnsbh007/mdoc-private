<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit;
}
require '../connect.php';

$_current = 'list_edoc_taseng.php';
$alert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);

// ── 2-level cascading filters: province -> district ─────────────────
$provinceFilter = $_GET['province_id'] ?? '';
$districtFilter = $_GET['district_id'] ?? '';

// Provinces with taseng counts (spot districts lacking taseng data)
$provinces = $conn->query("SELECT p.id, p.prov_code, p.name_lo,
                                  (SELECT COUNT(*) FROM edoc_taseng t JOIN edoc_district d ON t.district_id=d.id WHERE d.province_id=p.id) AS tas_cnt
                           FROM edoc_province p ORDER BY p.prov_code")->fetchAll(PDO::FETCH_ASSOC);

// Districts (filtered by province when selected) with taseng counts
$distSql = "SELECT d.id, d.dist_code, d.name_lo, d.province_id, p.prov_code, p.name_lo AS prov_name,
                   (SELECT COUNT(*) FROM edoc_taseng t WHERE t.district_id=d.id) AS tas_cnt
            FROM edoc_district d JOIN edoc_province p ON d.province_id=p.id";
$distParams = [];
if ($provinceFilter) { $distSql .= " WHERE d.province_id = ?"; $distParams[] = $provinceFilter; }
$distSql .= " ORDER BY p.prov_code, d.dist_code";
$distStmt = $conn->prepare($distSql);
$distStmt->execute($distParams);
$districts = $distStmt->fetchAll(PDO::FETCH_ASSOC);

// ── main list (province and/or district filter) ─────────────────────
$where = [];
$params = [];
if ($provinceFilter) { $where[] = "d.province_id = ?"; $params[] = $provinceFilter; }
if ($districtFilter) { $where[] = "t.district_id = ?"; $params[] = $districtFilter; }

$sql = "SELECT t.*, d.dist_code, d.name_lo AS dist_name, d.province_id, p.prov_code, p.name_lo AS prov_name
        FROM edoc_taseng t
        JOIN edoc_district d ON t.district_id = d.id
        JOIN edoc_province p ON d.province_id = p.id";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY p.prov_code, d.dist_code, t.tas_code";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$tasengs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($tasengs);

require '../components/layout.php';
?>

<style>
.prov-breadcrumb { font-size: 0.85rem; color: #666; margin-bottom: 10px; }
.prov-breadcrumb a { color: #044482; text-decoration: none; }
.prov-breadcrumb a:hover { text-decoration: underline; }
.prov-breadcrumb span { color: #bbb; margin: 0 6px; }
.btn-add-prov { background-color: #044482; color: white; border: none; padding: 9px 22px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 8px; }
.btn-add-prov:hover { background-color: #03305c; color: white; }
.prov-table-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); overflow: hidden; }
.prov-table-toolbar { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; border-bottom: 1px solid #f0f0f0; }
.prov-count { font-size: 0.9rem; color: #555; font-weight: 500; }
.prov-search-wrap { position: relative; }
.prov-search-wrap input { border: 1px solid #ddd; border-radius: 8px; padding: 7px 14px 7px 36px; font-size: 0.9rem; width: 230px; outline: none; transition: border 0.2s; }
.prov-search-wrap input:focus { border-color: #044482; }
.prov-search-wrap i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 0.85rem; }
#tasengTable { width: 100% !important; border-collapse: collapse; margin: 0 !important; }
#tasengTable thead tr th { background-color: #044482 !important; color: white !important; font-weight: 600; font-size: 0.88rem; padding: 12px 16px; border: none !important; position: sticky; top: 0; z-index: 5; }
#tasengTable tbody tr { border-bottom: 1px solid #f5f5f5; transition: background 0.15s; }
#tasengTable tbody tr:hover { background-color: #e8f0fb; }
#tasengTable tbody td { padding: 12px 16px; font-size: 0.9rem; color: #333; border: none !important; vertical-align: middle; }
.code-badge { background-color: #e6f0fa; color: #044482; border-radius: 6px; padding: 3px 11px; font-size: 0.82rem; font-weight: 700; display: inline-block; min-width: 44px; text-align: center; }
.dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_length { display: none !important; }
.dataTables_wrapper .dataTables_info { padding: 10px 20px; font-size: 0.82rem; color: #888; }
.dataTables_wrapper .dataTables_paginate { padding: 8px 20px; }
.dataTables_wrapper .paginate_button { border-radius: 6px !important; padding: 4px 10px !important; font-size: 0.83rem !important; }
.dataTables_wrapper .paginate_button.current { background: #044482 !important; color: white !important; border: none !important; }
</style>

<div class="container-fluid py-4">
  <div class="prov-breadcrumb">
    <a href="/edoc/index.php">ໜ້າຫຼັກ</a><span>/</span><a href="#">ຂໍ້ມູນພື້ນຖານ</a><span>/</span>
    ລາຍການຕາແສງ (Edoc Taseng)
  </div>

  <div class="apims-page-header d-flex justify-content-between align-items-end mb-3">
    <div>
      <div style="margin-bottom: 15px;">
        <h1 class="apims-page-title" style="font-weight: 700; font-size: 1.5rem; color: #03305c; margin: 0; display: flex; align-items: center; gap: 10px;">
          ລາຍການ ຕາແສງ (Taseng) - ກ່ຽວຂ້ອງ eDoc
        </h1>
      </div>
      <div class="action-buttons-top mb-3 d-flex gap-2">
        <a href="../index.php" class="btn btn-danger" title="ປິດ"><i class="fas fa-power-off"></i></a>
        <button class="btn btn-success" onclick="openAddTaseng()"><i class="fas fa-plus"></i> ເພີ່ມໃໝ່</button>
        <button class="btn btn-light border" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Refresh</button>
      </div>
    </div>
    <div class="prov-search-wrap mt-1">
      <i class="fas fa-search"></i>
      <input type="text" id="searchTaseng" placeholder="ຄົ້ນຫາຕາແສງ...">
    </div>
  </div>

  <div class="row g-2 mb-3">
    <div class="col-md-6">
      <label class="form-label mb-1">ແຂວງ (Province)</label>
      <select id="provinceFilter" class="form-select" onchange="filterGeo('province')">
        <option value="">-- ທຸກແຂວງ --</option>
        <?php foreach ($provinces as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $provinceFilter == $p['id'] ? 'selected' : '' ?>>
            [<?= htmlspecialchars($p['prov_code']) ?>] <?= htmlspecialchars($p['name_lo']) ?>
            (<?= (int)$p['tas_cnt'] ?> ຕາແສງ)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label mb-1">ເມືອງ (District)</label>
      <select id="districtFilter" class="form-select" onchange="filterGeo('district')">
        <option value="">-- ທຸກເມືອງ --</option>
        <?php foreach ($districts as $d): ?>
          <option value="<?= $d['id'] ?>" <?= $districtFilter == $d['id'] ? 'selected' : '' ?>>
            [<?= htmlspecialchars($d['prov_code']) ?>-<?= htmlspecialchars($d['dist_code']) ?>] <?= htmlspecialchars($d['dist_name']) ?>
            (<?= (int)$d['tas_cnt'] ?> ຕາແສງ)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="prov-table-card">
    <div style="overflow-y: auto; max-height: calc(100vh - 340px);">
      <table id="tasengTable" class="table mb-0">
        <thead>
          <tr>
            <th width="5%" class="text-center">ລຳດັບ</th>
            <th width="8%" class="text-center">ລະຫັດ</th>
            <th width="7%" class="text-center">nCode</th>
            <th width="13%">ແຂວງ</th>
            <th width="13%">ເມືອງ</th>
            <th>ຊື່ຕາແສງ</th>
            <th width="5%" class="text-center">ແກ້ໄຂ</th>
            <th width="5%" class="text-center">ລຶບ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tasengs as $i => $row): ?>
          <tr>
            <td class="text-center"><?= $i + 1 ?></td>
            <td class="text-center"><span class="code-badge"><?= htmlspecialchars($row['tas_code']) ?></span></td>
            <td class="text-center"><?= htmlspecialchars($row['ncode'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['prov_name']) ?> <small class="text-muted">[<?= htmlspecialchars($row['prov_code']) ?>]</small></td>
            <td><?= htmlspecialchars($row['dist_name']) ?> <small class="text-muted">[<?= htmlspecialchars($row['dist_code']) ?>]</small></td>
            <td><?= htmlspecialchars($row['name_lo']) ?></td>
            <td class="text-center">
              <button class="btn btn-warning" title="ແກ້ໄຂ" onclick="openEditTaseng(<?= $row['id'] ?>)"><i class="fas fa-edit"></i></button>
            </td>
            <td class="text-center">
              <button class="btn btn-danger" title="ລຶບ" onclick="deleteTaseng(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name_lo']) ?>')"><i class="fas fa-trash"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($tasengs)): ?>
          <tr><td colspan="8" class="text-center py-5" style="color:#aaa;"><i class="fas fa-folder-open fa-2x mb-2 d-block"></i> ຍັງບໍ່ມີຂໍ້ມູນຕາແສງ</td></tr>
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
require '../modal/modal_form_edoc_taseng.php';
require '../components/layout_footer.php';
?>

<script>
$(document).ready(function() {
    var table = $('#tasengTable').DataTable({
        paging: true, pageLength: 20, ordering: false, searching: true, info: true,
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/lo.json' }, dom: 'tip'
    });
    $('#searchTaseng').on('keyup', function() { table.search(this.value).draw(); });
});

// 2-level cascading filter: province -> district
function filterGeo(level) {
    var p = $('#provinceFilter').val();
    var d = $('#districtFilter').val();
    var qs = [];
    if (level === 'province') { if (p) qs.push('province_id=' + p); }
    else if (level === 'district') { if (p) qs.push('province_id=' + p); if (d) qs.push('district_id=' + d); }
    window.location.href = 'list_edoc_taseng.php' + (qs.length ? '?' + qs.join('&') : '');
}

function openAddTaseng() {
    $('#formTaseng')[0].reset();
    $('#tas_hidden_id').val('');
    $('#modalTasengTitle').html('ເພີ່ມຂໍ້ມູນຕາແສງໃໝ່ (eDoc)');
    var d = $('#districtFilter').val();
    if (d) $('#district_id').val(d);
    $.get('../ajax/search/get_next_edoc_tas_code.php', function(res) {
        try { let data = JSON.parse(res); if (data.status === 'success') $('#tas_code').val(data.next_code); } catch (e) {}
        new bootstrap.Modal(document.getElementById('modalFormTaseng')).show();
    });
}

function openEditTaseng(id) {
    $.ajax({
        url: '../ajax/search/get_edoc_taseng.php', type: 'POST', data: { id: id }, dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                let d = res.data;
                $('#formTaseng')[0].reset();
                $('#tas_hidden_id').val(d.id);
                $('#district_id').val(d.district_id);
                $('#tas_code').val(d.tas_code);
                $('#ncode').val(d.ncode ?? '');
                $('#tas_nm_l').val(d.name_lo);
                $('#tas_nm_e').val(d.name_en);
                $('#modalTasengTitle').html('ແກ້ໄຂຂໍ້ມູນຕາແສງ (eDoc)');
                new bootstrap.Modal(document.getElementById('modalFormTaseng')).show();
            } else { Swal.fire('ຜິດພາດ', res.message, 'error'); }
        }, error: function() { Swal.fire('ຜິດພາດ', 'ດຶງຂໍ້ມູນບໍ່ໄດ້!', 'error'); }
    });
}

function deleteTaseng(id, name) {
    Swal.fire({
        title: 'ຕ້ອງການລຶບຂໍ້ມູນແທ້ບໍ່?', text: name, icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#d33', cancelButtonColor: '#044482', confirmButtonText: 'ຕົກລົງລຶບ!', cancelButtonText: 'ຍົກເລີກ'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('../ajax/del/delete_edoc_taseng.php', { id: id }, function(res) {
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
