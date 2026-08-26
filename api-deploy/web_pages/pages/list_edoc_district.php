<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit;
}
require '../connect.php';
require_once '../edoc_auth.php';
if (!edoc_can_basic_data()) { die('ທ່ານບໍ່ມີສິດເຂົ້າເຖິງຂໍ້ມູນພື້ນຖານ / No access'); }

$_current = 'list_edoc_district.php';
$alert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);

// Fetch provinces for filter dropdown
$stmtProv = $conn->query("SELECT id, prov_code, name_lo FROM edoc_province ORDER BY prov_code ASC");
$provinces = $stmtProv->fetchAll(PDO::FETCH_ASSOC);

$provinceFilter = $_GET['province_id'] ?? '';
$where = '';
$params = [];
if ($provinceFilter) {
    $where = "WHERE d.province_id = ?";
    $params[] = $provinceFilter;
}

$sql = "SELECT d.*, p.prov_code, p.name_lo as prov_name_lo 
        FROM edoc_district d 
        JOIN edoc_province p ON d.province_id = p.id 
        $where 
        ORDER BY p.prov_code ASC, d.dist_code ASC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$districts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($districts);

require '../components/layout.php';
?>

<style>
.prov-breadcrumb { font-size: 0.85rem; color: #666; margin-bottom: 10px; }
.prov-breadcrumb a { color: #044482; text-decoration: none; }
.prov-breadcrumb a:hover { text-decoration: underline; }
.prov-breadcrumb span { color: #bbb; margin: 0 6px; }

.btn-add-prov {
    background-color: #044482; color: white; border: none;
    padding: 9px 22px; border-radius: 8px; font-weight: 600;
    font-size: 0.9rem; cursor: pointer; transition: background 0.2s;
    display: flex; align-items: center; gap: 8px;
}
.btn-add-prov:hover { background-color: #03305c; color: white; }

.prov-table-card {
    background: white; border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.07); overflow: hidden;
}
.prov-table-toolbar {
    display: flex; align-items: center;
    justify-content: space-between;
    padding: 14px 20px; border-bottom: 1px solid #f0f0f0;
}
.prov-count { font-size: 0.9rem; color: #555; font-weight: 500; }
.prov-search-wrap { position: relative; }
.prov-search-wrap input {
    border: 1px solid #ddd; border-radius: 8px;
    padding: 7px 14px 7px 36px; font-size: 0.9rem;
    width: 230px; outline: none; transition: border 0.2s;
}
.prov-search-wrap input:focus { border-color: #044482; }
.prov-search-wrap i {
    position: absolute; left: 11px; top: 50%;
    transform: translateY(-50%); color: #aaa; font-size: 0.85rem;
}

#districtTable { width: 100% !important; border-collapse: collapse; margin: 0 !important; }
#districtTable thead tr th {
    background-color: #044482 !important; color: white !important;
    font-weight: 600; font-size: 0.88rem; padding: 12px 16px;
    border: none !important;
    position: sticky; top: 0; z-index: 5;
}
#districtTable tbody tr { border-bottom: 1px solid #f5f5f5; transition: background 0.15s; }
#districtTable tbody tr:hover { background-color: #e8f0fb; }
#districtTable tbody td {
    padding: 12px 16px; font-size: 0.9rem; color: #333;
    border: none !important; vertical-align: middle;
}
.dist-code-badge {
    background-color: #e6f0fa; color: #044482;
    border-radius: 6px; padding: 3px 11px;
    font-size: 0.82rem; font-weight: 700;
    display: inline-block; min-width: 44px; text-align: center;
}

.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_length { display: none !important; }
.dataTables_wrapper .dataTables_info { padding: 10px 20px; font-size: 0.82rem; color: #888; }
.dataTables_wrapper .dataTables_paginate { padding: 8px 20px; }
.dataTables_wrapper .paginate_button { border-radius: 6px !important; padding: 4px 10px !important; font-size: 0.83rem !important; }
.dataTables_wrapper .paginate_button.current { background: #044482 !important; color: white !important; border: none !important; }
</style>

<div class="container-fluid py-4">

  <!-- Breadcrumb -->
  <div class="prov-breadcrumb">
    <a href="/edoc/index.php">ໜ້າຫຼັກ</a>
    <span>/</span>
    <a href="#">ຂໍ້ມູນພື້ນຖານ</a>
    <span>/</span>
    ລາຍການເມືອງ (Edoc Districts)
  </div>

  <!-- Title Row -->
  <div class="apims-page-header d-flex justify-content-between align-items-end mb-3">
    <div>
      <div style="margin-bottom: 15px;">
        <h1 class="apims-page-title" style="font-weight: 700; font-size: 1.5rem; color: #03305c; margin: 0; display: flex; align-items: center; gap: 10px;">
            ລາຍການ ເມືອງ (Districts) - ກ່ຽວຂ້ອງ eDoc
        </h1>
      </div>
      
      <div class="action-buttons-top mb-3 d-flex gap-2">
        <a href="../index.php" class="btn btn-danger" title="ປິດ"><i class="fas fa-power-off"></i></a>
        <button class="btn btn-success" onclick="openAddDistrict()"><i class="fas fa-plus"></i> ເພີ່ມໃໝ່</button>
        <a href="../print/print_edoc_districts.php" target="_blank" class="btn btn-light border"><i class="fas fa-print"></i> ພິມ</a>
        <button class="btn btn-light border" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Refresh</button>
      </div>
    </div>
  
    <div class="prov-search-wrap mt-1">
      <i class="fas fa-search"></i>    
      <input type="text" id="searchDistrict" placeholder="ຄົ້ນຫາເມືອງ...">
    </div>
  </div>

  <!-- Filter -->
  <div class="row mb-3">
    <div class="col-md-4">
      <select id="provinceFilter" class="form-select" onchange="filterByProvince()">
        <option value="">ທຸກແຂວງ</option>
        <?php foreach ($provinces as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $provinceFilter == $p['id'] ? 'selected' : '' ?>>
            [<?= htmlspecialchars($p['prov_code']) ?>] <?= htmlspecialchars($p['name_lo']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <!-- Card -->
  <div class="prov-table-card">

    <!-- Table -->
    <div style="overflow-y: auto; max-height: calc(100vh - 320px);">
      <table id="districtTable" class="table mb-0">
        <thead>
          <tr>
            <th width="5%"  class="text-center">ລຳດັບ</th>
            <th width="10%" class="text-center">ລະຫັດ</th>
            <th width="10%" class="text-center">ແຂວງ</th>
            <th width="10%" class="text-center">Off ID</th>
            <th>ຊື່ເມືອງ (ພາສາລາວ)</th>
            <th>ຊື່ເມືອງ (ພາສາອັງກິດ)</th>
            <th width="5%" class="text-center">ແກ້ໄຂ</th>
            <th width="5%" class="text-center">ລຶບ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($districts as $i => $row): ?>
          <tr>
            <td class="text-center"><?= $i + 1 ?></td>
            <td class="text-center">
              <span class="dist-code-badge"><?= htmlspecialchars($row['dist_code']) ?></span>
            </td>
            <td class="text-center"><?= htmlspecialchars($row['prov_code']) ?></td>
            <td class="text-center"><?= htmlspecialchars($row['off_id'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['name_lo']) ?></td>
            <td><?= htmlspecialchars($row['name_en'] ?? '-') ?></td>
            <td class="text-center">
              <button class="btn btn-warning" title="ແກ້ໄຂ" onclick="openEditDistrict(<?= $row['id'] ?>)"><i class="fas fa-edit"></i></button>
            </td>
            <td class="text-center">
              <button class="btn btn-danger" title="ລຶບ" onclick="deleteDistrict(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name_lo']) ?>')"><i class="fas fa-trash"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($districts)): ?>
          <tr>
            <td colspan="9" class="text-center py-5" style="color:#aaa;">
            <i class="fas fa-folder-open fa-2x mb-2 d-block"></i> ຍັງບໍ່ມີຂໍ້ມູນເມືອງ
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    
    <div class="p-3 bg-light border-top" style="border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
      <div class="prov-count">
        ທັງໝົດ <strong><?= $total ?></strong> ລາຍການ
      </div>
    </div>
  </div><!-- end card -->
</div>

<?php
require '../modal/modal_form_edoc_district.php';
require '../components/layout_footer.php';
?>

<script>
$(document).ready(function() {
    var table = $('#districtTable').DataTable({
        paging: true, pageLength: 20, ordering: false,
        searching: true, info: true,
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/lo.json' },
        dom: 'tip'
    });
    $('#searchDistrict').on('keyup', function() {
        table.search(this.value).draw();
    });
});

function filterByProvince() {
    var provId = $('#provinceFilter').val();
    window.location.href = 'list_edoc_district.php' + (provId ? '?province_id=' + provId : '');
}

function openAddDistrict() {
    $('#formDistrict')[0].reset();
    $('#dist_hidden_id').val('');
    $('#modalDistrictTitle').html('ເພີ່ມຂໍ້ມູນເມືອງໃໝ່ (eDoc)');
    
    // Pre-select province if filtered
    var provId = $('#provinceFilter').val();
    if (provId) {
        $('#province_id').val(provId);
    }
    
    $.get('../ajax/search/get_next_edoc_dist_code.php', function(res) {
        try {
            let data = JSON.parse(res);
            if (data.status === 'success') {
                $('#dist_code').val(data.next_code);
            }
        } catch (e) {}
        new bootstrap.Modal(document.getElementById('modalFormDistrict')).show();
    });
}

function openEditDistrict(id) {
    $.ajax({
        url: '../ajax/search/get_edoc_district.php',
        type: 'POST', data: { id: id }, dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                let d = res.data;
                $('#formDistrict')[0].reset();
                $('#dist_hidden_id').val(d.id);
                $('#province_id').val(d.province_id);
                $('#dist_code').val(d.dist_code);
                $('#off_id').val(d.off_id ?? '');
                $('#dist_nm_l').val(d.name_lo);
                $('#dist_nm_e').val(d.name_en);
                $('#modalDistrictTitle').html('ແກ້ໄຂຂໍ້ມູນເມືອງ (eDoc)');
                new bootstrap.Modal(document.getElementById('modalFormDistrict')).show();
            } else {
                Swal.fire('ຜິດພາດ', res.message, 'error');
            }
        },
        error: function() { Swal.fire('ຜິດພາດ', 'ດຶງຂໍ້ມູນບໍ່ໄດ້!', 'error'); }
    });
}

function deleteDistrict(id, name) {
    Swal.fire({
        title: 'ຕ້ອງການລຶບຂໍ້ມູນແທ້ບໍ່?', text: name, icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33', cancelButtonColor: '#044482',
        confirmButtonText: 'ຕົກລົງລຶບ!', cancelButtonText: 'ຍົກເລີກ'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('../ajax/del/delete_edoc_district.php', { id: id }, function(res) {
                try {
                    let data = JSON.parse(res);
                    if (data.status === 'success') {
                        Swal.fire({title: 'ລຶບແລ້ວ!', text: data.message, icon: 'success', timer: 1500, showConfirmButton: false}).then(() => location.reload());
                    } else { Swal.fire('ຜິດພາດ', data.message, 'error'); }
                } catch(e) { Swal.fire('ຜິດພາດ', 'ເກີດຂໍ້ຜິດພາດ', 'error'); }
            });
        }
    });
}

<?php if ($alert): ?>
Swal.fire({
    icon: '<?= $alert['type'] ?>',
    title: '<?= $alert['type'] === 'success' ? 'ສຳເລັດ!' : 'ແຈ້ງເຕືອນ!' ?>',
    text: '<?= addslashes($alert['message']) ?>',
    confirmButtonColor: '#044482', confirmButtonText: 'ຕົກລົງ'
});
<?php endif; ?>
</script>