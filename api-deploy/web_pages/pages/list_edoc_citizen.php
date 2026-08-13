<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require '../connect.php';

$_current = 'list_edoc_citizen.php';
$alert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);

$stmt = $conn->query("SELECT c.*, v.name_lo AS village_name, t.name_lo AS taseng_name, d.name_lo AS district_name, p.name_lo AS province_name
                      FROM edoc_citizen c
                      LEFT JOIN edoc_village v ON c.village_id = v.id
                      LEFT JOIN edoc_taseng t ON v.tasseng_id = t.id
                      LEFT JOIN edoc_district d ON t.district_id = d.id
                      LEFT JOIN edoc_province p ON d.province_id = p.id
                      ORDER BY c.id DESC");
$citizens = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($citizens);

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
#citizenTable { width: 100% !important; border-collapse: collapse; margin: 0 !important; }
#citizenTable thead tr th { background-color: #044482 !important; color: white !important; font-weight: 600; font-size: 0.85rem; padding: 11px 14px; border: none !important; position: sticky; top: 0; z-index: 5; }
#citizenTable tbody tr { border-bottom: 1px solid #f5f5f5; transition: background 0.15s; }
#citizenTable tbody tr:hover { background-color: #e8f0fb; }
#citizenTable tbody td { padding: 11px 14px; font-size: 0.88rem; color: #333; border: none !important; vertical-align: middle; }
.dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_length { display: none !important; }
.dataTables_wrapper .dataTables_info { padding: 10px 20px; font-size: 0.82rem; color: #888; }
.dataTables_wrapper .dataTables_paginate { padding: 8px 20px; }
.dataTables_wrapper .paginate_button.current { background: #044482 !important; color: white !important; border: none !important; }
.badge-f { background:#fce7f3;color:#9d174d; } .badge-m { background:#dbeafe;color:#1e40af; }
</style>

<div class="container-fluid py-4">
  <div class="prov-breadcrumb">
    <a href="/edoc/index.php">ໜ້າຫຼັກ</a><span>/</span><a href="#">ບໍລິການເອກະສານ</a><span>/</span>ຂໍ້ມູນພົນລະເມືອງ (Citizens)
  </div>

  <div class="apims-page-header d-flex justify-content-between align-items-end mb-3">
    <div>
      <h1 class="apims-page-title" style="font-weight: 700; font-size: 1.5rem; color: #03305c; margin: 0;">
        ຂໍ້ມູນພົນລະເມືອງ (Citizens) - eDoc
      </h1>
      <div class="action-buttons-top mb-3 mt-3 d-flex gap-2">
        <a href="../index.php" class="btn btn-danger" title="ປິດ"><i class="fas fa-power-off"></i></a>
        <button class="btn btn-success" onclick="openAddCitizen()"><i class="fas fa-plus"></i> ເພີ່ມໃໝ່</button>
        <button class="btn btn-light border" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Refresh</button>
      </div>
    </div>
    <div class="prov-search-wrap mt-1">
      <i class="fas fa-search"></i>
      <input type="text" id="searchCitizen" placeholder="ຄົ້ນຫາຊື່/ເບີໂທ...">
    </div>
  </div>

  <div class="prov-table-card">
    <div style="overflow-y: auto; max-height: calc(100vh - 300px);">
      <table id="citizenTable" class="table mb-0">
        <thead>
          <tr>
            <th width="5%" class="text-center">#</th>
            <th>ຊື່-ນາມສະກຸນ</th>
            <th width="8%" class="text-center">ເພດ</th>
            <th>ເບີໂທ</th>
            <th>ທີ່ຢູ່ (ບ້ານ/ຕາແສງ/ເມືອງ/ແຂວງ)</th>
            <th width="5%" class="text-center">ແກ້ໄຂ</th>
            <th width="5%" class="text-center">ລຶບ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($citizens as $i => $row): ?>
          <tr>
            <td class="text-center"><?= $i + 1 ?></td>
            <td>
              <strong><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></strong>
              <?php if ($row['id_card']): ?><br><small class="text-muted">ບັດປະຈຳຕົວ: <?= htmlspecialchars($row['id_card']) ?></small><?php endif; ?>
            </td>
            <td class="text-center">
              <?php if ($row['gender'] === 'F'): ?><span class="badge badge-f">ຍ</span>
              <?php elseif ($row['gender'] === 'M'): ?><span class="badge badge-m">ຊ</span>
              <?php else: ?>-<?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['phone'] ?? '-') ?></td>
            <td>
              <?= htmlspecialchars($row['village_name'] ?? '-') ?>
              <?php if ($row['address_line']): ?>, <?= htmlspecialchars($row['address_line']) ?><?php endif; ?>
              <br><small class="text-muted"><?= htmlspecialchars(trim(($row['taseng_name'] ?? '') . ' / ' . ($row['district_name'] ?? '') . ' / ' . ($row['province_name'] ?? ''))) ?></small>
            </td>
            <td class="text-center"><button class="btn btn-warning" onclick="openEditCitizen(<?= $row['id'] ?>)"><i class="fas fa-edit"></i></button></td>
            <td class="text-center"><button class="btn btn-danger" onclick="deleteCitizen(<?= $row['id'] ?>, '<?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>')"><i class="fas fa-trash"></i></button></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($citizens)): ?>
          <tr><td colspan="7" class="text-center py-5" style="color:#aaa;"><i class="fas fa-folder-open fa-2x mb-2 d-block"></i> ຍັງບໍ່ມີຂໍ້ມູນພົນລະເມືອງ</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="p-3 bg-light border-top" style="border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
      <div class="prov-count">ທັງໝົດ <strong><?= $total ?></strong> ຄົນ</div>
    </div>
  </div>
</div>

<?php
require '../modal/modal_form_edoc_citizen.php';
require '../components/layout_footer.php';
?>

<script>
$(document).ready(function() {
    var table = $('#citizenTable').DataTable({ paging: true, pageLength: 20, ordering: false, searching: true, info: true, language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/lo.json' }, dom: 'tip' });
    $('#searchCitizen').on('keyup', function() { table.search(this.value).draw(); });
});

function loadGeo(prev, targetSel, selected) {
    if (!prev) { $(targetSel).html('<option value="">-- ເລືອກ --</option>'); return; }
    $.get('../ajax/search/get_edoc_geo.php', { type: prev.type, id: prev.id }, function(res) {
        try { let d = JSON.parse(res); if (d.status === 'success') {
            let h = '<option value="">-- ເລືອກ --</option>';
            d.data.forEach(r => h += `<option value="${r.id}">${r.name_lo}</option>`);
            $(targetSel).html(h); if (selected) $(targetSel).val(selected);
        }} catch(e){}
    });
}

function resetGeo() {
    $('#cit_province').val(''); $('#cit_district').html('<option value="">-- ເລືອງ --</option>');
    $('#cit_taseng').html('<option value="">-- ເລືອງ --</option>'); $('#cit_village').html('<option value="">-- ເລືອງ --</option>');
}

function openAddCitizen() {
    $('#formCitizen')[0].reset();
    $('#cit_hidden_id').val('');
    resetGeo();
    $('#modalCitizenTitle').html('ເພີ່ມພົນລະເມືອງໃໝ່');
    new bootstrap.Modal(document.getElementById('modalFormCitizen')).show();
}

function openEditCitizen(id) {
    $.ajax({ url: '../ajax/search/get_edoc_citizen.php', type: 'POST', data: { id: id }, dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                let d = res.data;
                $('#formCitizen')[0].reset();
                $('#cit_hidden_id').val(d.id);
                $('#cit_first').val(d.first_name); $('#cit_last').val(d.last_name);
                $('#cit_gender').val(d.gender); $('#cit_dob').val(d.dob);
                $('#cit_phone').val(d.phone); $('#cit_email').val(d.email);
                $('#cit_idcard').val(d.id_card); $('#cit_addr').val(d.address_line);
                resetGeo();
                if (d.village_id) {
                    // load chain by village
                    $.get('../ajax/search/get_edoc_geo_path.php', { village_id: d.village_id }, function(r2){
                        try { let p = JSON.parse(r2); if (p.status==='success'){
                            $('#cit_province').val(p.data.province_id);
                            loadGeo({type:'province',id:p.data.province_id}, '#cit_district', p.data.district_id);
                            setTimeout(()=>loadGeo({type:'district',id:p.data.district_id}, '#cit_taseng', p.data.taseng_id),300);
                            setTimeout(()=>loadGeo({type:'taseng',id:p.data.taseng_id}, '#cit_village', d.village_id),600);
                        }}catch(e){}
                    });
                }
                $('#modalCitizenTitle').html('ແກ້ໄຂພົນລະເມືອງ');
                new bootstrap.Modal(document.getElementById('modalFormCitizen')).show();
            } else { Swal.fire('ຜິດພາດ', res.message, 'error'); }
        }, error: function() { Swal.fire('ຜິດພາດ', 'ດຶງຂໍ້ມູນບໍ່ໄດ້!', 'error'); }
    });
}

function deleteCitizen(id, name) {
    Swal.fire({ title: 'ຕ້ອງການລຶບຂໍ້ມູນແທ້ບໍ່?', text: name, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#044482', confirmButtonText: 'ຕົກລົງລຶບ!', cancelButtonText: 'ຍົກເລີກ' }).then((r) => {
        if (r.isConfirmed) {
            $.post('../ajax/del/delete_edoc_citizen.php', { id: id }, function(res) {
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