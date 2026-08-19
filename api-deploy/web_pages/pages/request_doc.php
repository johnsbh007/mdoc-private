<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require_once '../connect.php';
$pageTitle = "ຄຳຮ້ອງໃໝ່ - New Request";
$_current = 'request_doc.php';
include '../components/layout.php';

// edit mode: load an existing draft on the same 2-tab page (same data as add-new)
$edit_doc_id = !empty($_GET['doc_id']) ? (int)$_GET['doc_id'] : 0;
$editData = null;   // joined citizen + doc (+ doc_type/item)
$editForm = [];     // field_id => saved value
if ($edit_doc_id) {
    $st = $conn->prepare("SELECT c.*, d.doc_no, d.status, d.amount, di.doc_type_id, di.id AS doc_item_id
                          FROM edoc_document d
                          JOIN edoc_doc_item di ON d.doc_item_id = di.id
                          JOIN edoc_citizen c ON d.citizen_id = c.id
                          WHERE d.id = ?");
    $st->execute([$edit_doc_id]);
    $editData = $st->fetch(PDO::FETCH_ASSOC);
    if ($editData) {
        $pageTitle = "ແກ້ໄຂຮ່າງ - Edit Draft";
        // resolve type/item from the doc itself
        $doc_type_id = (int)$editData['doc_type_id'];
        $t = $conn->prepare("SELECT id, doc_type_code, name_lo, name_en FROM edoc_doc_type WHERE id=? AND is_active=1");
        $t->execute([$doc_type_id]);
        $type = $t->fetch(PDO::FETCH_ASSOC);
        $doc_item_id = (int)$editData['doc_item_id'];
        $item = $conn->prepare("SELECT id, doc_type_id, doc_item_code, name_lo FROM edoc_doc_item WHERE id=? AND is_active=1");
        $item->execute([$doc_item_id]);
        $item = $item->fetch(PDO::FETCH_ASSOC);
        // saved doc-type field values
        $fd = $conn->prepare("SELECT field_id, field_value FROM edoc_form_data WHERE doc_id=?");
        $fd->execute([$edit_doc_id]);
        foreach ($fd->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $editForm['field_' . $f['field_id']] = $f['field_value'];
        }
    }
}

// new-request mode: resolve type/item from GET params (edit mode resolved them from the doc above)
if (!$editData) {
    $doc_type_id = !empty($_GET['doc_type_id']) ? (int)$_GET['doc_type_id'] : 0;
    $type = null;
    if ($doc_type_id) {
        $t = $conn->prepare("SELECT id, doc_type_code, name_lo, name_en FROM edoc_doc_type WHERE id=? AND is_active=1");
        $t->execute([$doc_type_id]);
        $type = $t->fetch(PDO::FETCH_ASSOC);
    }
    if (!$type) {
        echo '<div class="content-wrapper p-3"><div class="alert alert-warning">ບໍ່ພົບປະເພດເອກະສານ. <a href="select_doc_type.php">ເລືອກປະເພດ</a></div></div>';
        include '../components/layout_footer.php';
        exit;
    }
    // default doc_item for this type (explicit if provided, else first active item)
    $doc_item_id = !empty($_GET['doc_item_id']) ? (int)$_GET['doc_item_id'] : 0;
    if ($doc_item_id) {
        $item = $conn->prepare("SELECT id, doc_type_id, doc_item_code, name_lo FROM edoc_doc_item WHERE id=? AND doc_type_id=? AND is_active=1");
        $item->execute([$doc_item_id, $doc_type_id]);
        $item = $item->fetch(PDO::FETCH_ASSOC);
    }
    if (!$item) {
        $item = $conn->prepare("SELECT id, doc_type_id, doc_item_code, name_lo FROM edoc_doc_item WHERE doc_type_id=? AND is_active=1 ORDER BY id LIMIT 1");
        $item->execute([$doc_type_id]);
        $item = $item->fetch(PDO::FETCH_ASSOC);
    }
}
// mode: 'draft' = normal New Request (allow save-as-draft), 'submit' = walk-in citizen request (submit only, becomes SUBMITTED)
$mode = in_array(($_GET['mode'] ?? ''), ['draft','submit']) ? $_GET['mode'] : 'draft';
$isSubmit = ($mode === 'submit');
$provinces = $conn->query("SELECT id, name_lo FROM edoc_province ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// lookup tables for basic person data (save code, show name)
$countries   = $conn->query("SELECT code, name_lo, name_en FROM edoc_country WHERE is_active=1 ORDER BY name_lo")->fetchAll(PDO::FETCH_ASSOC);
$ethnicities = $conn->query("SELECT code, name_lo FROM edoc_ethnicity WHERE is_active=1 ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
$religions   = $conn->query("SELECT code, name_lo FROM edoc_religion WHERE is_active=1 ORDER BY name_lo")->fetchAll(PDO::FETCH_ASSOC);
$occupations = $conn->query("SELECT code, name_lo FROM edoc_occupation WHERE is_active=1 ORDER BY name_lo")->fetchAll(PDO::FETCH_ASSOC);
?>

<script>
// lookup arrays (code -> display name)
const LOOKUP = {
  country:   <?= json_encode(array_column($countries, 'name_lo', 'code'), JSON_UNESCAPED_UNICODE) ?>,
  ethnicity: <?= json_encode(array_column($ethnicities, 'name_lo', 'code'), JSON_UNESCAPED_UNICODE) ?>,
  religion:  <?= json_encode(array_column($religions, 'name_lo', 'code'), JSON_UNESCAPED_UNICODE) ?>,
  occupation:<?= json_encode(array_column($occupations, 'name_lo', 'code'), JSON_UNESCAPED_UNICODE) ?>
};
// provinces for the 4-level geo cascade (id -> name)
const PROVINCES = <?= json_encode(array_column($provinces, 'name_lo', 'id'), JSON_UNESCAPED_UNICODE) ?>;
function optgroup(label, map){ let h='<option value="">-- '+label+' --</option>'; for(const k in map){ h+='<option value="'+k+'">'+map[k]+'</option>'; } return h; }
function fillSelect(id, map, label){ const el=document.getElementById(id); if(el){ el.innerHTML = optgroup(label, map); } }
// cascading geo: parentId -> childId (province->district->taseng->village) via get_edoc_geo.php
// geoType normalizes both current-address, birth_, and parent_ prefixed ids
function geoType(id){ const n = id.replace(/^(birth_|parent_)/, ''); return {province_id:'province', district_id:'district', taseng_id:'taseng'}[n] || 'province'; }
function loadGeo(parentId, childId) {
  const parent = document.getElementById(parentId);
  const child  = document.getElementById(childId);
  if (!parent || !child) return;
  const pid = parent.value;
  // clear child and all its descendants (via GEO_CHAIN map)
  let curId = childId;
  while (curId) {
    const el = document.getElementById(curId);
    if (!el) break;
    el.innerHTML = '<option value="">-- ເລືອກ --</option>';
    curId = GEO_CHAIN[curId];
  }
  if (!pid) return;
  const type = geoType(parentId);
  $.get('../ajax/search/get_edoc_geo.php', {type, id: pid}, function(res){
    const r = parseJson(res);
    if (!r || r.status !== 'success') return;
    let h = '<option value="">-- ເລືອກ --</option>';
    r.data.forEach(o => { h += '<option value="'+o.id+'">'+o.name_lo+'</option>'; });
    child.innerHTML = h;
  });
}
// add parent_ to GEO_CHAIN and presetGeo logic
const GEO_CHAIN = { province_id:'district_id', district_id:'taseng_id', taseng_id:'village_id',
                    birth_province_id:'birth_district_id', birth_district_id:'birth_taseng_id', birth_taseng_id:'birth_village_id',
                    parent_province_id:'parent_district_id', parent_district_id:'parent_taseng_id', parent_taseng_id:'parent_village_id' };
// preset the whole cascade from stored codes (NID lookup / edit)
function presetGeo(prefix, c) {
  const ids = [prefix+'province_id', prefix+'district_id', prefix+'taseng_id', prefix+'village_id'];
  const vals = [c[prefix+'province_id'], c[prefix+'district_id'], c[prefix+'taseng_id'], c[prefix+'village_id']];
  const el0 = document.getElementById(ids[0]);
  if (!el0) return;
  el0.value = vals[0] || '';
  let idx = 1;
  function next() {
    if (idx >= ids.length) return;
    const pid = document.getElementById(ids[idx-1]);
    const child = document.getElementById(ids[idx]);
    if (!child) return;
    child.innerHTML = '<option value="">-- ເລືອກ --</option>';
    if (!pid || !pid.value) return;
    const type = geoType(pid.id);
    $.get('../ajax/search/get_edoc_geo.php', {type, id: pid.value}, function(res){
      const r = parseJson(res);
      if (!r || r.status !== 'success') return;
      let h = '<option value="">-- ເລືອກ --</option>';
      r.data.forEach(o => { h += '<option value="'+o.id+'">'+o.name_lo+'</option>'; });
      child.innerHTML = h;
      child.value = vals[idx] || '';
      idx++;
      next();
    });
  }
  next();
}
</script>

<div class="content-wrapper p-3">
  <div class="card">
    <div class="card-header bg-primary text-white">
      <?= $editData ? "ແກ້ໄຂຮ່າງ" : "ຄຳຮ້ອງໃໝ່" ?>
    </div>
    <div class="card-body">
      <h6>ຂໍ້ມູນເອກະສານ</h6>
      <div id="docFields"></div>
    </div>
  </div>
</div>
<!-- prepare dropdowns -->
<script>
console.log('DEBUG: Ready, provinces=', PROVINCES);
$(function(){
  fillSelect('parent_province_id', PROVINCES, 'ເລືອກຂໍ້');
  loadDocFields();
});
</script>