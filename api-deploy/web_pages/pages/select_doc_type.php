<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require_once '../connect.php';
require_once '../edoc_auth.php';
if (!edoc_can_create()) { die('ທ່ານບໍ່ມີສິດສ້າງຄຳຮ້ອງ / No permission'); }
$pageTitle = "ເລືອກປະເພດເອກະສານ - New Request";
$_current = 'select_doc_type.php';
// mode: 'draft' (normal New Request) or 'submit' (walk-in citizen request) -> request_doc only submits
$mode = in_array(($_GET['mode'] ?? ''), ['draft','submit']) ? $_GET['mode'] : 'draft';
include '../components/layout.php';

// Level 1: document types
$types = $conn->query("SELECT id, doc_type_code, name_lo, name_en
                       FROM edoc_doc_type WHERE is_active=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// Level 2: items grouped by type (all active items)
$items = $conn->query("SELECT di.id, di.doc_type_id, di.doc_item_code, di.name_lo, di.name_en,
                              dt.doc_type_code, dt.name_lo AS type_name
                       FROM edoc_doc_item di
                       JOIN edoc_doc_type dt ON di.doc_type_id = dt.id
                       WHERE di.is_active=1 AND dt.is_active=1
                       ORDER BY dt.id, di.doc_item_code")->fetchAll(PDO::FETCH_ASSOC);
$itemsByType = [];
foreach ($items as $it) { $itemsByType[$it['doc_type_id']][] = $it; }
?>
<style>
.step-ind { font-size:.8rem; color:#888; margin-bottom:4px; }
.type-card { transition: transform .12s, box-shadow .12s; }
.type-card:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(0,0,0,.12); }
.item-card { transition: transform .12s, box-shadow .12s; border-left:4px solid #044482; }
.item-card:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(0,0,0,.12); }
.hidden { display:none !important; }
</style>

<div class="content-wrapper p-3">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="list_edoc_document.php">ບໍລິການເອກະສານ</a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= $mode==='submit' ? 'ຄຳຮ້ອງໃໝ່ (ພົນລະເມືອງຂໍມາ)' : 'ເລືອກປະເພດ' ?></li>
    </ol>
  </nav>

  <!-- LEVEL 1: choose document type -->
  <div id="typePanel">
    <div class="card">
      <div class="card-header bg-primary text-white">
        <?= $mode==='submit' ? 'ຂັ້ນທີ 1: ເລືອກປະເພດເອກະສານ (ພົນລະເມືອງຂໍມາ) / Step 1: Select Document Type' : 'ຂັ້ນທີ 1: ເລືອກປະເພດເອກະສານ / Step 1: Select Document Type' ?>
      </div>
      <div class="card-body">
        <p class="text-muted">ກະລຸນາເລືອກປະເພດເອກະສານທີ່ຕ້ອງການ.</p>
        <div class="row g-3" id="typeGrid">
          <?php foreach ($types as $t): ?>
          <?php $cnt = count($itemsByType[$t['id']] ?? []); ?>
          <div class="col-md-4">
            <div class="card h-100 border type-card" style="cursor:pointer"
                 onclick="chooseType(<?= $t['id'] ?>)">
              <div class="card-body">
                <span class="badge bg-secondary mb-2">[<?= htmlspecialchars($t['doc_type_code']) ?>]</span>
                <h6 class="card-title mb-1"><?= htmlspecialchars($t['name_lo']) ?></h6>
                <small class="text-muted"><?= htmlspecialchars($t['name_en']) ?></small>
                <div class="mt-2"><span class="badge bg-light text-dark border"><?= $cnt ?> ລາຍການ</span></div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- LEVEL 2: choose document item (refreshed by type) -->
  <div id="itemPanel" class="hidden">
    <div class="card">
      <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <span>ຂັ້ນທີ 2: ເລືອກລາຍການເອກະສານ / Step 2: Select Document Item</span>
        <button class="btn btn-sm btn-light" onclick="backToTypes()"><i class="fas fa-arrow-left"></i> ກັບຄືນ</button>
      </div>
      <div class="card-body">
        <p class="text-muted">ປະເພດ: <strong id="selTypeName"></strong> &mdash; ກະລຸນາເລືອກລາຍການ.</p>
        <div class="row g-3" id="itemGrid"></div>
      </div>
    </div>
  </div>
</div>

<script>
const ITEMS_BY_TYPE = <?= json_encode($itemsByType, JSON_UNESCAPED_UNICODE) ?>;
const TYPES = <?= json_encode(array_column($types, 'name_lo', 'id'), JSON_UNESCAPED_UNICODE) ?>;
const MODE = <?= json_encode($mode, JSON_UNESCAPED_UNICODE) ?>;

function chooseType(typeId) {
  const list = ITEMS_BY_TYPE[typeId];
  if (!list || !list.length) {
    Swal.fire('ບໍ່ມີລາຍການ', 'ປະເພດນີ້ຍັງບໍ່ມີລາຍການເອກະສານ.', 'info');
    return;
  }
  document.getElementById('selTypeName').textContent = TYPES[typeId] || '';
  let h = '';
  list.forEach(function(it){
    h += '<div class="col-md-4">'
       +   '<div class="card h-100 border item-card" style="cursor:pointer" '
       +   'onclick="chooseItem(' + it.doc_type_id + ',' + it.id + ')">'
       +     '<div class="card-body">'
       +       '<span class="badge bg-secondary mb-2">[' + escapeHtml(it.doc_item_code) + ']</span>'
       +       '<h6 class="card-title mb-1">' + escapeHtml(it.name_lo) + '</h6>'
       +       '<small class="text-muted">' + escapeHtml(it.name_en || '') + '</small>'
       +     '</div>'
       +   '</div>'
       + '</div>';
  });
  document.getElementById('itemGrid').innerHTML = h;
  document.getElementById('typePanel').classList.add('hidden');
  document.getElementById('itemPanel').classList.remove('hidden');
  window.scrollTo({top:0, behavior:'smooth'});
}

function chooseItem(typeId, itemId) {
  window.location.href = 'request_doc.php?doc_type_id=' + typeId + '&doc_item_id=' + itemId + '&mode=' + encodeURIComponent(MODE);
}

function backToTypes() {
  document.getElementById('itemPanel').classList.add('hidden');
  document.getElementById('typePanel').classList.remove('hidden');
  window.scrollTo({top:0, behavior:'smooth'});
}

function escapeHtml(s){ return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
</script>
<?php include '../components/layout_footer.php'; ?>
