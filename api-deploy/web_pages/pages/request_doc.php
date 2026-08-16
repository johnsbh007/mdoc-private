<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require_once '../connect.php';
$pageTitle = "ຄຳຮ້ອງໃໝ່ - New Request";
$_current = 'request_doc.php';
include '../components/layout.php';

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
  const type = {province_id:'province', district_id:'district', taseng_id:'taseng'}[parentId] || 'province';
  $.get('../ajax/search/get_edoc_geo.php', {type, id: pid}, function(res){
    const r = parseJson(res);
    if (!r || r.status !== 'success') return;
    let h = '<option value="">-- ເລືອກ --</option>';
    r.data.forEach(o => { h += '<option value="'+o.id+'">'+o.name_lo+'</option>'; });
    child.innerHTML = h;
  });
}
const GEO_CHAIN = { province_id:'district_id', district_id:'taseng_id', taseng_id:'village_id',
                    birth_province_id:'birth_district_id', birth_district_id:'birth_taseng_id', birth_taseng_id:'birth_village_id' };
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
    const type = {province_id:'province', district_id:'district', taseng_id:'taseng'}[pid.id] || 'province';
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
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="list_edoc_document.php">ບໍລິການເອກະສານ</a></li>
      <li class="breadcrumb-item"><a href="select_doc_type.php">ເລືອກປະເພດ</a></li>
      <li class="breadcrumb-item"><a href="select_doc_type.php"><?= htmlspecialchars($type['name_lo']) ?></a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($item['name_lo']) ?></li>
    </ol>
  </nav>

  <div class="card">
    <div class="card-header bg-primary text-white">
      <?= $isSubmit ? 'ຄຳຮ້ອງໃໝ່ (ພົນລະເມືອງຂໍມາ)' : 'ຄຳຮ້ອງໃໝ່: ' . htmlspecialchars($item['name_lo']) ?>
      <span class="badge bg-light text-dark">[<?= htmlspecialchars($type['doc_type_code']) ?>]</span>
    </div>
    <div class="card-body">
      <!-- hidden shared fields -->
      <input type="hidden" id="doc_type_id" value="<?= $type['id'] ?>">
      <input type="hidden" id="doc_item_id" value="<?= $item['id'] ?>">
      <input type="hidden" id="citizen_id" value="">

      <ul class="nav nav-tabs mb-3" id="reqTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="tab-person" data-bs-toggle="tab" data-bs-target="#p-person" type="button" role="tab">1. ຂໍ້ມູນພົນລະເມືອງ (Person)</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-doc" data-bs-toggle="tab" data-bs-target="#p-doc" type="button" role="tab">2. ຂໍ້ມູນເອກະສານ (Document)</button>
        </li>
      </ul>

      <div class="tab-content">
        <!-- TAB 1: PERSON -->
        <div class="tab-pane fade show active" id="p-person" role="tabpanel">
          <div class="row g-2 mb-3">
            <div class="col-md-4">
              <label class="form-label">ເລກບັດປະຈຳຕົວ (National ID)</label>
              <div class="input-group">
                <input type="text" id="nid" class="form-control" placeholder="ປ້ອນເລກບັດ...">
                <button class="btn btn-outline-secondary" type="button" onclick="lookupNid()">ຄົ້ນຫາ</button>
              </div>
              <small id="nidMsg" class="text-muted"></small>
            </div>
          </div>
          <div class="row g-2">
            <div class="col-md-2">
              <label class="form-label">ຄຳນຳ (Title)</label>
              <select id="title" class="form-select">
                <option value="">--</option><option value="ທ້າວ">ທ້າວ</option><option value="ນາງ">ນາງ</option>
              </select>
            </div>
            <div class="col-md-3"><label class="form-label">ຊື່</label><input id="first_name" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">ນາມສະກຸນ</label><input id="last_name" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">ເພດ</label>
              <select id="gender" class="form-select"><option value="">--</option><option value="M">ຊາຍ</option><option value="F">ຍິງ</option></select>
            </div>
            <div class="col-md-2"><label class="form-label">ວັນເກີດ</label><input type="date" id="dob" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">ເບີໂທ</label><input id="phone" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">ອີເມວ</label><input id="email" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">ສັນຊາດ (Nationality)</label><select id="nationality" class="form-select"></select></div>
            <div class="col-md-2"><label class="form-label">ເຊື້ອຊາດ (Origin)</label><select id="origin" class="form-select"></select></div>
            <div class="col-md-2"><label class="form-label">ຊົນເຜົ່າ (Ethnicity)</label><select id="ethnicity" class="form-select"></select></div>
            <div class="col-md-2"><label class="form-label">ສາສະໜາ (Religion)</label><select id="religion" class="form-select"></select></div>
            <div class="col-md-3"><label class="form-label">ອາຊີບ (Occupation)</label><select id="occupation" class="form-select"></select></div>
            <div class="col-md-3"><label class="form-label">ວັນອອກບັດ</label><input type="date" id="id_card_issue_date" class="form-control"></div>
          </div>
          <hr>
          <h6>ທີ່ຢູ່ປັດຈຸບັນ (Current address)</h6>
          <div class="row g-2">
            <div class="col-md-3">
              <label class="form-label">ແຂວງ (Province)</label>
              <select id="province_id" class="form-select" onchange="loadGeo('province_id','district_id')"></select>
            </div>
            <div class="col-md-3">
              <label class="form-label">ເມືອງ (District)</label>
              <select id="district_id" class="form-select" onchange="loadGeo('district_id','taseng_id')"></select>
            </div>
            <div class="col-md-3">
              <label class="form-label">ຕາແສງ (Taseng)</label>
              <select id="taseng_id" class="form-select" onchange="loadGeo('taseng_id','village_id')"></select>
            </div>
            <div class="col-md-3">
              <label class="form-label">ບ້ານ (Village)</label>
              <select id="village_id" class="form-select"></select>
            </div>
            <div class="col-md-2"><label class="form-label">ເລກບ້ານ</label><input id="house_no" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">ໜ່ວຍ</label><input id="unit_no" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">ທີ່ຢູ່ເພີ່ມເຕີມ</label><input id="address_line" class="form-control"></div>
          </div>
          <hr>
          <h6>ສະຖານທີ່ເກີດ (Birth place)</h6>
          <div class="row g-2">
            <div class="col-md-3">
              <label class="form-label">ແຂວງເກີດ (Province)</label>
              <select id="birth_province_id" class="form-select" onchange="loadGeo('birth_province_id','birth_district_id')"></select>
            </div>
            <div class="col-md-3">
              <label class="form-label">ເມືອງເກີດ (District)</label>
              <select id="birth_district_id" class="form-select" onchange="loadGeo('birth_district_id','birth_taseng_id')"></select>
            </div>
            <div class="col-md-3">
              <label class="form-label">ຕາແສງເກີດ (Taseng)</label>
              <select id="birth_taseng_id" class="form-select" onchange="loadGeo('birth_taseng_id','birth_village_id')"></select>
            </div>
            <div class="col-md-3">
              <label class="form-label">ບ້ານເກີດ (Village)</label>
              <select id="birth_village_id" class="form-select"></select>
            </div>
          </div>
        </div>

        <!-- TAB 2: DOCUMENT (rendered from edoc_doc_field for this doc_type) -->
        <div class="tab-pane fade" id="p-doc" role="tabpanel">
          <div id="docFields"><p class="text-muted">ກະລຸນາເລືອກປະເພດ ແລະ ປ້ອນຂໍ້ມູນພົນລະເມືອງກ່ອນ.</p></div>
        </div>
      </div>

      <div class="d-flex gap-2 mt-3">
        <?php if (!$isSubmit): ?>
        <button class="btn btn-secondary" onclick="saveRequest('draft')"><i class="fas fa-save"></i> ບັນທຶກເປັນຮ່າງ (Draft)</button>
        <?php endif; ?>
        <button class="btn btn-primary" onclick="saveRequest('submit')"><i class="fas fa-paper-plane"></i> <?= $isSubmit ? 'ບັນທຶກຄຳຮ້ອງ (Submit)' : 'ສົ່ງຄຳຮ້ອງ (Submit)' ?></button>
        <a href="select_doc_type.php?mode=<?= $mode ?>" class="btn btn-light border">ຍົກເລິກ</a>
      </div>
    </div>
  </div>
</div>

<script>
const DOC_TYPE_ID = <?= $type['id'] ?>;
const DOC_ITEM_ID = <?= $item['id'] ?>;

// parseJson helper (jQuery auto-parses JSON responses)
function parseJson(res){ return (typeof res==='object' && res!==null) ? res : (()=>{try{return JSON.parse(res)}catch(e){return null}})(); }

function lookupNid() {
  const nid = document.getElementById('nid').value.trim();
  if (!nid) return;
  document.getElementById('nidMsg').textContent = 'ກຳລັງຄົ້ນຫາ...';
  $.get('../ajax/search/get_citizen_by_nid.php', {nid}, function(res){
    const r = parseJson(res);
    if (r && r.status === 'success') {
      fillPerson(r.data);
      document.getElementById('nidMsg').textContent = 'ພົບຂໍ້ມູນ: ' + (r.data.full_name || '');
    } else {
      document.getElementById('nidMsg').textContent = 'ບໍ່ພົບ (ປ້ອນໃໝ່).';
    }
  });
}

function fillPerson(c) {
  const map = {first_name:'first_name',last_name:'last_name',gender:'gender',dob:'dob',phone:'phone',email:'email',
    nationality:'nationality',origin:'origin',ethnicity:'ethnicity',religion:'religion',occupation:'occupation',
    id_card_issue_date:'id_card_issue_date',house_no:'house_no',unit_no:'unit_no',
    address_line:'address_line'};
  for (const [id,k] of Object.entries(map)) {
    const el = document.getElementById(id);
    if (el && c[k] != null) el.value = c[k];
  }
  // 4-level geo cascades (current address + birth place) — preset from stored codes
  presetGeo('', c);        // province_id/district_id/taseng_id/village_id
  presetGeo('birth_', c);  // birth_province_id/birth_district_id/birth_taseng_id/birth_village_id
  document.getElementById('citizen_id').value = c.id || '';
}

// load Tab 2 fields when doc type known (on page load)
function loadDocFields() {
  $.get('../ajax/search/get_edoc_docitem_fields.php', {doc_item_id: DOC_ITEM_ID}, function(res){
    const r = parseJson(res);
    if (!r || r.status !== 'success') { return; }
    let h = '';
    r.data.fields.forEach(f => {
      const lbl = f.field_label;
      const name = 'field_' + f.id;
      if (f.field_type === 'TEXTAREA') {
        h += `<div class="mb-2"><label class="form-label">${lbl}</label><textarea name="${name}" class="form-control" rows="2"></textarea></div>`;
      } else if (f.field_type === 'DATE') {
        h += `<div class="mb-2"><label class="form-label">${lbl}</label><input type="date" name="${name}" class="form-control"></div>`;
      } else {
        h += `<div class="mb-2"><label class="form-label">${lbl}</label><input type="text" name="${name}" class="form-control"></div>`;
      }
    });
    document.getElementById('docFields').innerHTML = h;
  });
}

function saveRequest(mode) {
  const data = {
    mode,
    doc_item_id: DOC_ITEM_ID,
    citizen_id: document.getElementById('citizen_id').value || 0,
    manual_id_card: document.getElementById('nid').value,
    manual_first_name: document.getElementById('first_name').value,
    manual_last_name: document.getElementById('last_name').value,
    manual_gender: document.getElementById('gender').value,
    manual_dob: document.getElementById('dob').value,
    manual_phone: document.getElementById('phone').value,
    manual_email: document.getElementById('email').value,
    nationality: document.getElementById('nationality').value,
    origin: document.getElementById('origin').value,
    ethnicity: document.getElementById('ethnicity').value,
    religion: document.getElementById('religion').value,
    occupation: document.getElementById('occupation').value,
    id_card_issue_date: document.getElementById('id_card_issue_date').value,
    house_no: document.getElementById('house_no').value,
    unit_no: document.getElementById('unit_no').value,
    province_id: document.getElementById('province_id').value || null,
    district_id: document.getElementById('district_id').value || null,
    taseng_id: document.getElementById('taseng_id').value || null,
    village_id: document.getElementById('village_id').value || null,
    manual_address_line: document.getElementById('address_line').value,
    birth_province_id: document.getElementById('birth_province_id').value || null,
    birth_district_id: document.getElementById('birth_district_id').value || null,
    birth_taseng_id: document.getElementById('birth_taseng_id').value || null,
    birth_village_id: document.getElementById('birth_village_id').value || null,
  };
  // doc-type fields
  document.querySelectorAll('#docFields [name^="field_"]').forEach(el => { data[el.name] = el.value; });
  $.ajax({
    url: '../insert/insert_edoc_document.php',
    method: 'POST',
    data,
    headers: {'X-Requested-With': 'XMLHttpRequest'},
    success: function(res){
      const r = parseJson(res);
      if (r && r.status === 'success') {
        Swal.fire('ສຳເລັດ', r.message, 'success').then(() => { window.location.href='list_edoc_document.php?status=' + (mode==='draft'?'DRAFT':'SUBMITTED'); });
      } else {
        Swal.fire('ຜິດພາດ', (r && r.message) || 'ເກີດຂໍ້ຜິດພາດ', 'error');
      }
    },
    error: function(){ Swal.fire('ຜິດພາດ','ເກີດຂໍ້ຜິດພາດ','error'); }
  });
}

$(function(){
  fillSelect('nationality', LOOKUP.country, 'ເລືອກສັນຊາດ');
  fillSelect('origin', LOOKUP.country, 'ເລືອກເຊື້ອຊາດ');
  fillSelect('ethnicity', LOOKUP.ethnicity, 'ເລືອກຊົນເຜົ່າ');
  fillSelect('religion', LOOKUP.religion, 'ເລືອກສາສະໜາ');
  fillSelect('occupation', LOOKUP.occupation, 'ເລືອກອາຊີບ');
  // 4-level geo cascade: province options (children load on change)
  fillSelect('province_id', PROVINCES, 'ເລືອກແຂວງ');
  fillSelect('birth_province_id', PROVINCES, 'ເລືອກແຂວງ');
  // sensible default: Lao
  if (document.getElementById('nationality')) document.getElementById('nationality').value = 'LA';
  if (document.getElementById('origin')) document.getElementById('origin').value = 'LA';
  loadDocFields();
});
</script>
<?php include '../components/layout_footer.php'; ?>
