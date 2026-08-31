<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require_once '../connect.php';
require_once '../edoc_auth.php';
if (!edoc_can_create()) { die('ທ່ານບໍ່ມີສິດສ້າງແກ້ໄຂຄຳຮ້ອງ / No permission'); }
$pageTitle = "ຄຳຮ້ອງໃໝ່ - New Request";
$_current = 'request_doc.php';
include '../components/layout.php';

// edit mode: load an existing draft on the same 2-tab page (same data as add-new)
$edit_doc_id = !empty($_GET['doc_id']) ? (int)$_GET['doc_id'] : 0;
$editData = null;   // joined citizen + doc (+ doc_type/item)
$editForm = [];     // field_id => saved value
if ($edit_doc_id) {
    $st = $conn->prepare("SELECT c.*, d.doc_no, d.status, d.amount, d.template_variant, di.doc_type_id, di.id AS doc_item_id,
                                 b.id AS buyer_id, b.id_card AS buyer_id_card, b.first_name AS buyer_first_name,
                                 b.last_name AS buyer_last_name, b.title AS buyer_title, b.gender AS buyer_gender,
                                 b.dob AS buyer_dob, b.phone AS buyer_phone, b.email AS buyer_email,
                                 b.nationality AS buyer_nationality, b.origin AS buyer_origin,
                                 b.ethnicity AS buyer_ethnicity, b.religion AS buyer_religion,
                                 b.occupation AS buyer_occupation, b.id_card_issue_date AS buyer_id_card_issue_date,
                                 b.house_no AS buyer_house_no, b.unit_no AS buyer_unit_no,
                                 b.address_line AS buyer_address_line, b.province_id AS buyer_province_id,
                                 b.district_id AS buyer_district_id, b.taseng_id AS buyer_taseng_id,
                                 b.village_id AS buyer_village_id, b.birth_province_id AS buyer_birth_province_id,
                                 b.birth_district_id AS buyer_birth_district_id, b.birth_taseng_id AS buyer_birth_taseng_id,
                                 b.birth_village_id AS buyer_birth_village_id
                          FROM edoc_document d
                          JOIN edoc_doc_item di ON d.doc_item_id = di.id
                          JOIN edoc_citizen c ON d.citizen_id = c.id
                          LEFT JOIN edoc_citizen b ON d.buyer_id = b.id
                          WHERE d.id = ?");
    $st->execute([$edit_doc_id]);
    $editData = $st->fetch(PDO::FETCH_ASSOC);
    if ($editData) {
        $pageTitle = "ແກ້ໄຂຮ່າງ - Edit Draft";
        // expose buyer citizen object to JS for prefill (buyer_* columns -> nested buyer)
        if (!empty($editData['buyer_id'])) {
            $editData['buyer'] = [
                'id' => $editData['buyer_id'], 'id_card' => $editData['buyer_id_card'],
                'first_name' => $editData['buyer_first_name'], 'last_name' => $editData['buyer_last_name'],
                'title' => $editData['buyer_title'], 'gender' => $editData['buyer_gender'],
                'dob' => $editData['buyer_dob'], 'phone' => $editData['buyer_phone'], 'email' => $editData['buyer_email'],
                'nationality' => $editData['buyer_nationality'], 'origin' => $editData['buyer_origin'],
                'ethnicity' => $editData['buyer_ethnicity'], 'religion' => $editData['buyer_religion'],
                'occupation' => $editData['buyer_occupation'], 'id_card_issue_date' => $editData['buyer_id_card_issue_date'],
                'house_no' => $editData['buyer_house_no'], 'unit_no' => $editData['buyer_unit_no'],
                'address_line' => $editData['buyer_address_line'], 'province_id' => $editData['buyer_province_id'],
                'district_id' => $editData['buyer_district_id'], 'taseng_id' => $editData['buyer_taseng_id'],
                'village_id' => $editData['buyer_village_id'], 'birth_province_id' => $editData['buyer_birth_province_id'],
                'birth_district_id' => $editData['buyer_birth_district_id'], 'birth_taseng_id' => $editData['buyer_birth_taseng_id'],
                'birth_village_id' => $editData['buyer_birth_village_id'],
            ];
        }
        // merge residence-specific structured fields (unit + geo) so the edit
        // form can pre-populate them (Bug: Unit/geo lost on re-edit)
        if ((int)$editData['doc_type_id'] === 1) {
            $r = $conn->prepare("SELECT * FROM edoc_dc_residence WHERE doc_id = ?");
            $r->execute([$edit_doc_id]);
            $res = $r->fetch(PDO::FETCH_ASSOC);
            if ($res) {
                foreach ($res as $k => $v) { $editData[$k] = $v; }
            }
        }
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
// mode: 'draft' = normal New Request (allow save-as-draft), 'submit' = walk-in citizen request (submit only, becomes PENDING_APPROVAL)
$mode = in_array(($_GET['mode'] ?? ''), ['draft','submit']) ? $_GET['mode'] : 'draft';
$isSubmit = ($mode === 'submit');

// ── hard gate: only implemented items may be opened for entry ──
// (SSR mirror of select_doc_type.php; blocks direct URL access to unfinished styles)
if (!$item || !edoc_item_implemented($item['id'])) {
    echo '<div class="content-wrapper p-3"><div class="alert alert-warning">'
       . 'ລາຍການເອກະສານນີ້ຍັງບໍ່ທັນພ້ອມໃຊ້ງານ (ກຳລັງພັດທະນາ) / This document form is under construction. '
       . '<a href="select_doc_type.php">ເລືອກລາຍການອື່ນ</a></div></div>';
    include '../components/layout_footer.php';
    exit;
}
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
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="list_edoc_document.php">ບໍລິການເອກະສານ</a></li>
      <?php if ($editData): ?>
      <li class="breadcrumb-item"><a href="list_edoc_document.php?status=DRAFT">ຮ່າງຄຳຮ້ອງ</a></li>
      <li class="breadcrumb-item active" aria-current="page">ແກ້ໄຂຮ່າງ: <?= htmlspecialchars($item['name_lo']) ?></li>
      <?php else: ?>
      <li class="breadcrumb-item"><a href="select_doc_type.php">ເລືອກປະເພດ</a></li>
      <li class="breadcrumb-item"><a href="select_doc_type.php"><?= htmlspecialchars($type['name_lo']) ?></a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($item['name_lo']) ?></li>
      <?php endif; ?>
    </ol>
  </nav>

  <div class="card">
    <div class="card-header bg-primary text-white">
      <?php if ($editData): ?>
        ແກ້ໄຂຮ່າງ (Edit Draft): <?= htmlspecialchars($item['name_lo']) ?>
        <span class="badge bg-light text-dark">[<?= htmlspecialchars($editData['doc_no'] ?? '') ?>]</span>
      <?php elseif ($isSubmit): ?>
        ຄຳຮ້ອງໃໝ່ (ພົນລະເມືອງຂໍມາ)
      <?php else: ?>
        ຄຳຮ້ອງໃໝ່: <?= htmlspecialchars($item['name_lo']) ?>
      <?php endif; ?>
      <span class="badge bg-light text-dark">[<?= htmlspecialchars($type['doc_type_code']) ?>]</span>
    </div>
    <div class="card-body">
      <!-- hidden shared fields -->
      <input type="hidden" id="doc_type_id" value="<?= $type['id'] ?>">
      <input type="hidden" id="doc_item_id" value="<?= $item['id'] ?>">
      <input type="hidden" id="doc_hidden_id" value="<?= $edit_doc_id ?>">
      <input type="hidden" id="citizen_id" value="">

<?php
// Dynamic tab labels per item (pre-existing bug: all items showed Seller/Buyer/Other)
$tab1Label = '1. ຂໍ້ມູນ (Person)';
$tab2Label = '2. ຂໍ້ມູນ (Party 2)';
$tab3Label = '3. ຂໍ້ມູນເອກະສານ (Document)';
$party2Label = 'ຜູ້ຊື້ (Buyer)';
if ($item['id'] == 52 || $item['id'] == 53) { // land-sale certificate / land sale contract
    $tab1Label = '1. ຂໍ້ມູນຜູ້ຂາຍ (Seller)';
    $tab2Label = '2. ຂໍ້ມູນຜູ້ຊື້ (Buyer)';
    $tab3Label = '3. ຂໍ້ມູນອື່ນໆ (Other)';
    $party2Label = 'ຜູ້ຊື້ (Buyer)';
} elseif ($item['id'] == 28) { // loan contract
    $tab1Label = '1. ຝ່າຍ ກໍ ຜູ້ໃຫ້ກູ້ (Party A - Lender)';
    $tab2Label = '2. ຝ່າຍ ຂໍ ຜູ້ຢືມ (Party B - Borrower)';
    $tab3Label = '3. ລາຍລະອຽດສັນຍາ (Contract terms)';
    $party2Label = 'ຜູ້ຢືມ (Borrower)';
} elseif ($item['id'] == 30) { // lease contract
    $tab1Label = '1. ເຈົ້າຂອງຊັບ (Owner/Landlord)';
    $tab2Label = '2. ຜູ້ເຂົ້າເຊົ່າ (Renter/Tenant)';
    $tab3Label = '3. ລາຍລະອຽດສັນຍາ (Contract terms)';
    $party2Label = 'ຜູ້ເຂົ້າເຊົ່າ (Renter)';
} elseif ($item['id'] == 20) { // marriage request
    $tab1Label = '1. ຜູ້ຊາຍ (Groom)';
    $tab2Label = '2. ຜູ້ຍິງ (Bride)';
    $tab3Label = '3. ລາຍລະອຽດຄຳຮ້ອງ (Request details)';
    $party2Label = 'ເຈົ້າສາວ (Bride)';
} elseif ($item['id'] == 58) { // divorce record
    $tab1Label = '1. ຜົວ (Husband)';
    $tab2Label = '2. ເມຍ (Wife)';
    $tab3Label = '3. ລາຍລະອຽດບັນທຶກ (Record details)';
    $party2Label = 'ເມຍ (Wife)';
}
?>

      <ul class="nav nav-tabs mb-3" id="reqTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="tab-person" data-bs-toggle="tab" data-bs-target="#p-person" type="button" role="tab"><?= $tab1Label ?></button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-buyer" data-bs-toggle="tab" data-bs-target="#p-buyer" type="button" role="tab"><?= $tab2Label ?></button>
        </li>
<?php if (!in_array($item['id'], [52, 28, 53, 30, 20, 58], true)): /* hide the party-2 tab for single-party items */ ?>
        <style>#tab-buyer { display:none !important; } #p-buyer { display:none !important; }</style>
<?php endif; ?>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-doc" data-bs-toggle="tab" data-bs-target="#p-doc" type="button" role="tab"><?= $tab3Label ?></button>
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

        <!-- TAB 2: BUYER (real citizen record, keyed by national ID) -->
        <div class="tab-pane fade" id="p-buyer" role="tabpanel">
          <div class="row g-2 mb-3">
            <div class="col-md-4">
              <label class="form-label">ເລກບັດປະຈຳຕົວ <?= $party2Label ?> (National ID)</label>
              <div class="input-group">
                <input type="text" id="b_nid" class="form-control" placeholder="ປ້ອນເລກບັດ...">
                <button class="btn btn-outline-secondary" type="button" onclick="lookupBuyerNid()">ຄົ້ນຫາ</button>
              </div>
              <small id="b_nidMsg" class="text-muted"></small>
            </div>
          </div>
          <div class="row g-2">
            <div class="col-md-2">
              <label class="form-label">ຄຳນຳ (Title)</label>
              <select id="b_title" class="form-select">
                <option value="">--</option><option value="ທ້າວ">ທ້າວ</option><option value="ນາງ">ນາງ</option>
              </select>
            </div>
            <div class="col-md-3"><label class="form-label">ຊື່</label><input id="b_first_name" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">ນາມສະກຸນ</label><input id="b_last_name" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">ເພດ</label>
              <select id="b_gender" class="form-select"><option value="">--</option><option value="M">ຊາຍ</option><option value="F">ຍິງ</option></select>
            </div>
            <div class="col-md-2"><label class="form-label">ວັນເກີດ</label><input type="date" id="b_dob" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">ເບີໂທ</label><input id="b_phone" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">ອີເມວ</label><input id="b_email" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">ສັນຊາດ (Nationality)</label><select id="b_nationality" class="form-select"></select></div>
            <div class="col-md-2"><label class="form-label">ເຊື້ອຊາດ (Origin)</label><select id="b_origin" class="form-select"></select></div>
            <div class="col-md-2"><label class="form-label">ຊົນເຜົ່າ (Ethnicity)</label><select id="b_ethnicity" class="form-select"></select></div>
            <div class="col-md-2"><label class="form-label">ສາສະໜາ (Religion)</label><select id="b_religion" class="form-select"></select></div>
            <div class="col-md-3"><label class="form-label">ອາຊີບ (Occupation)</label><select id="b_occupation" class="form-select"></select></div>
            <div class="col-md-3"><label class="form-label">ວັນອອກບັດ</label><input type="date" id="b_id_card_issue_date" class="form-control"></div>
          </div>
          <hr>
          <h6>ທີ່ຢູ່ປັດຈຸບັນ <?= $party2Label ?> (Current address)</h6>
          <div class="row g-2">
            <div class="col-md-3">
              <label class="form-label">ແຂວງ (Province)</label>
              <select id="b_province_id" class="form-select" onchange="loadGeo('b_province_id','b_district_id')"></select>
            </div>
            <div class="col-md-3">
              <label class="form-label">ເມືອງ (District)</label>
              <select id="b_district_id" class="form-select" onchange="loadGeo('b_district_id','b_taseng_id')"></select>
            </div>
            <div class="col-md-3">
              <label class="form-label">ຕາແສງ (Taseng)</label>
              <select id="b_taseng_id" class="form-select" onchange="loadGeo('b_taseng_id','b_village_id')"></select>
            </div>
            <div class="col-md-3">
              <label class="form-label">ບ້ານ (Village)</label>
              <select id="b_village_id" class="form-select"></select>
            </div>
            <div class="col-md-2"><label class="form-label">ເລກບ້ານ</label><input id="b_house_no" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">ໜ່ວຍ</label><input id="b_unit_no" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">ທີ່ຢູ່ເພີ່ມເຕີມ</label><input id="b_address_line" class="form-control"></div>
          </div>
          <hr>
          <h6>ສະຖານທີ່ເກີດ <?= $party2Label ?> (Birth place)</h6>
          <div class="row g-2">
            <div class="col-md-3">
              <label class="form-label">ແຂວງເກີດ (Province)</label>
              <select id="b_birth_province_id" class="form-select" onchange="loadGeo('b_birth_province_id','b_birth_district_id')"></select>
            </div>
            <div class="col-md-3">
              <label class="form-label">ເມືອງເກີດ (District)</label>
              <select id="b_birth_district_id" class="form-select" onchange="loadGeo('b_birth_district_id','b_birth_taseng_id')"></select>
            </div>
            <div class="col-md-3">
              <label class="form-label">ຕາແສງເກີດ (Taseng)</label>
              <select id="b_birth_taseng_id" class="form-select" onchange="loadGeo('b_birth_taseng_id','b_birth_village_id')"></select>
            </div>
            <div class="col-md-3">
              <label class="form-label">ບ້ານເກີດ (Village)</label>
              <select id="b_birth_village_id" class="form-select"></select>
            </div>
          </div>
          <input type="hidden" id="buyer_citizen_id" value="">
        </div>

        <!-- TAB 3: DOCUMENT (rendered from edoc_doc_field for this doc_type) -->
        <div class="tab-pane fade" id="p-doc" role="tabpanel">
          <?php if ($item['id'] == 8): // biography: choose short or full version ?>
          <div class="mb-3 p-3 bg-light rounded">
            <label class="form-label fw-bold">ແບບຟອມຊີວະປະຫວັດ (Biography template):</label>
            <select class="form-select" id="template_variant">
              <option value="full" <?= ($editData['template_variant'] ?? '') === 'full' ? 'selected' : '' ?>>ແບບຟອມເຕັມຫຼາຍໜ້າ — Full (multi-page)</option>
              <option value="short" <?= ($editData['template_variant'] ?? '') === 'short' ? 'selected' : '' ?>>ແບບຟອມສັ້ນ — Short (one-page)</option>
            </select>
          </div>
          <?php else: ?>
          <input type="hidden" id="template_variant" value="">
          <?php endif; ?>
          <div id="docFields"><p class="text-muted">ກະລຸນາເລືອກປະເພດ ແລະ ປ້ອນຂໍ້ມູນພົນລະເມືອງກ່ອນ.</p></div>
        </div>
      </div>

      <div class="d-flex gap-2 mt-3">
        <?php if (!$isSubmit): ?>
        <button class="btn btn-secondary" onclick="saveRequest('draft')"><i class="fas fa-save"></i> <?= $editData ? 'ບັນທຶກຮ່າງ (Save Draft)' : 'ບັນທຶກເປັນຮ່າງ (Draft)' ?></button>
        <?php endif; ?>
        <button class="btn btn-primary" onclick="saveRequest('submit')"><i class="fas fa-paper-plane"></i> <?= $editData ? 'ສົ່ງຄຳຮ້ອງ (Submit)' : ($isSubmit ? 'ບັນທຶກຄຳຮ້ອງ (Submit)' : 'ສົ່ງຄຳຮ້ອງ (Submit)') ?></button>
        <a href="<?= $editData ? 'list_edoc_document.php?status=DRAFT' : 'select_doc_type.php?mode=' . $mode ?>" class="btn btn-light border">ຍົກເລິກ</a>
      </div>
    </div>
  </div>
</div>

<script>
const DOC_TYPE_ID = <?= $type['id'] ?>;
const DOC_ITEM_ID = <?= $item['id'] ?>;
const EDIT_DATA = <?= $editData ? json_encode($editData, JSON_UNESCAPED_UNICODE) : 'null' ?>;
const EDIT_FORM = <?= json_encode($editForm, JSON_UNESCAPED_UNICODE) ?>;
const EDIT_DOC_ID = <?= $edit_doc_id ?>;

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
  presetGeo('', c);
  presetGeo('birth_', c);
  document.getElementById('citizen_id').value = c.id || '';
}

function lookupBuyerNid() {
  const nid = document.getElementById('b_nid').value.trim();
  if (!nid) return;
  document.getElementById('b_nidMsg').textContent = 'ກຳລັງຄົ້ນຫາ...';
  $.get('../ajax/search/get_citizen_by_nid.php', {nid}, function(res){
    const r = parseJson(res);
    if (r && r.status === 'success') {
      fillBuyer(r.data);
      document.getElementById('b_nidMsg').textContent = 'ພົບຂໍ້ມູນ: ' + (r.data.full_name || '');
    } else {
      document.getElementById('b_nidMsg').textContent = 'ບໍ່ພົບ (ປ້ອນໃໝ່).';
    }
  });
}

function fillBuyer(c) {
  const map = {b_first_name:'first_name',b_last_name:'last_name',b_gender:'gender',b_dob:'dob',b_phone:'phone',b_email:'email',
    b_nationality:'nationality',b_origin:'origin',b_ethnicity:'ethnicity',b_religion:'religion',b_occupation:'occupation',
    b_id_card_issue_date:'id_card_issue_date',b_house_no:'house_no',b_unit_no:'unit_no',
    b_address_line:'address_line'};
  for (const [id,k] of Object.entries(map)) {
    const el = document.getElementById(id);
    if (el && c[k] != null) el.value = c[k];
  }
  presetGeo('b_', c);
  presetGeo('b_birth_', c);
  document.getElementById('buyer_citizen_id').value = c.id || '';
}

function loadDocFields() {
  $.get('../ajax/search/get_edoc_docitem_fields.php', {doc_item_id: DOC_ITEM_ID}, function(res){
    const r = parseJson(res);
    if (!r || r.status !== 'success') { return; }
    let h = '';
    r.data.fields.forEach(f => {
      const lbl = f.field_label;
      const name = 'field_' + f.id;
      // parents_address (field_id 11) -> structured geo cascade
      if (f.id == 11) {
        h += `
        <div class="card mb-2 border">
          <div class="card-header py-1 bg-light"><strong>${lbl}</strong></div>
          <div class="card-body py-2">
            <div class="row g-2">
              <div class="col-md-2"><label class="form-label small">ໜ່ວຍ</label><input type="text" id="parents_unit_no" name="parents_unit_no" class="form-control form-control-sm"></div>
              <div class="col-md-2"><label class="form-label small">ແຂວງ</label><select id="parent_province_id" name="parent_province_id" class="form-select form-select-sm" onchange="loadGeo('parent_province_id','parent_district_id')"></select></div>
              <div class="col-md-3"><label class="form-label small">ເມືອງ</label><select id="parent_district_id" name="parent_district_id" class="form-select form-select-sm" onchange="loadGeo('parent_district_id','parent_taseng_id')"></select></div>
              <div class="col-md-2"><label class="form-label small">ຕາແສງ</label><select id="parent_taseng_id" name="parent_taseng_id" class="form-select form-select-sm" onchange="loadGeo('parent_taseng_id','parent_village_id')"></select></div>
              <div class="col-md-3"><label class="form-label small">ບ້ານ</label><select id="parent_village_id" name="parent_village_id" class="form-select form-select-sm"></select></div>
            </div>
            <input type="hidden" name="${name}" id="field_${f.id}" value="STRUCTURED">
          </div>
        </div>`;
        return;
      }
      if (f.field_type === 'TEXTAREA') {
        h += `<div class="mb-2"><label class="form-label">${lbl}</label><textarea name="${name}" class="form-control" rows="2"></textarea></div>`;
      } else if (f.field_type === 'DATE') {
        h += `<div class="mb-2"><label class="form-label">${lbl}</label><input type="date" name="${name}" class="form-control"></div>`;
      } else {
        h += `<div class="mb-2"><label class="form-label">${lbl}</label><input type="text" name="${name}" class="form-control"></div>`;
      }
    });
    document.getElementById('docFields').innerHTML = h;
    fillSelect('parent_province_id', PROVINCES, 'ເລືອກແຂວງ');

    if (EDIT_FORM && Object.keys(EDIT_FORM).length) {
      Object.entries(EDIT_FORM).forEach(([name, val]) => {
        const el = document.querySelector('#docFields [name="' + name + '"]');
        if (el) el.value = val;
      });
    }
    // Pre-fill structured parents geo directly from EDIT_DATA (residence table
    // merged into $editData). Runs whenever residence geo exists, regardless of
    // EDIT_FORM, so re-edited change requests keep Unit/province/.../village.
    if (EDIT_DATA && (EDIT_DATA.parents_province_id || EDIT_DATA.parents_unit_no)) {
      if (document.getElementById('parents_unit_no')) document.getElementById('parents_unit_no').value = EDIT_DATA.parents_unit_no || '';
      // Residence columns are parents_* (with "s") but the form field ids are
      // parent_* (without "s"); map them so presetGeo finds the stored values.
      const pd = {
        parent_province_id: EDIT_DATA.parents_province_id,
        parent_district_id: EDIT_DATA.parents_district_id,
        parent_taseng_id:   EDIT_DATA.parents_taseng_id,
        parent_village_id:  EDIT_DATA.parents_village_id,
      };
      presetGeo('parent_', pd);
    }
  });
}

function saveRequest(mode) {
  const data = {
    template_variant: document.getElementById('template_variant') ? document.getElementById('template_variant').value : '',
    mode,
    doc_item_id: DOC_ITEM_ID,
    id: EDIT_DOC_ID || 0,
    citizen_id: document.getElementById('citizen_id').value || 0,
    manual_id_card: document.getElementById('nid').value,
    manual_first_name: document.getElementById('first_name').value,
    manual_last_name: document.getElementById('last_name').value,
    title: document.getElementById('title') ? document.getElementById('title').value : '',
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
    // Parents address fields
    parents_unit_no: document.getElementById('parents_unit_no') ? document.getElementById('parents_unit_no').value : null,
    parents_province_id: document.getElementById('parent_province_id') ? document.getElementById('parent_province_id').value : null,
    parents_district_id: document.getElementById('parent_district_id') ? document.getElementById('parent_district_id').value : null,
    parents_taseng_id: document.getElementById('parent_taseng_id') ? document.getElementById('parent_taseng_id').value : null,
    parents_village_id: document.getElementById('parent_village_id') ? document.getElementById('parent_village_id').value : null,
    // Buyer (real citizen record, keyed by national ID)
    buyer_citizen_id: document.getElementById('buyer_citizen_id') ? document.getElementById('buyer_citizen_id').value || 0 : 0,
    b_manual_id_card: document.getElementById('b_nid') ? document.getElementById('b_nid').value : '',
    b_first_name: document.getElementById('b_first_name') ? document.getElementById('b_first_name').value : '',
    b_last_name: document.getElementById('b_last_name') ? document.getElementById('b_last_name').value : '',
    b_title: document.getElementById('b_title') ? document.getElementById('b_title').value : '',
    b_gender: document.getElementById('b_gender') ? document.getElementById('b_gender').value : '',
    b_dob: document.getElementById('b_dob') ? document.getElementById('b_dob').value : '',
    b_phone: document.getElementById('b_phone') ? document.getElementById('b_phone').value : '',
    b_email: document.getElementById('b_email') ? document.getElementById('b_email').value : '',
    b_nationality: document.getElementById('b_nationality') ? document.getElementById('b_nationality').value : '',
    b_origin: document.getElementById('b_origin') ? document.getElementById('b_origin').value : '',
    b_ethnicity: document.getElementById('b_ethnicity') ? document.getElementById('b_ethnicity').value : '',
    b_religion: document.getElementById('b_religion') ? document.getElementById('b_religion').value : '',
    b_occupation: document.getElementById('b_occupation') ? document.getElementById('b_occupation').value : '',
    b_id_card_issue_date: document.getElementById('b_id_card_issue_date') ? document.getElementById('b_id_card_issue_date').value : '',
    b_house_no: document.getElementById('b_house_no') ? document.getElementById('b_house_no').value : '',
    b_unit_no: document.getElementById('b_unit_no') ? document.getElementById('b_unit_no').value : '',
    b_province_id: document.getElementById('b_province_id') ? document.getElementById('b_province_id').value || null : null,
    b_district_id: document.getElementById('b_district_id') ? document.getElementById('b_district_id').value || null : null,
    b_taseng_id: document.getElementById('b_taseng_id') ? document.getElementById('b_taseng_id').value || null : null,
    b_village_id: document.getElementById('b_village_id') ? document.getElementById('b_village_id').value || null : null,
    b_address_line: document.getElementById('b_address_line') ? document.getElementById('b_address_line').value : '',
    b_birth_province_id: document.getElementById('b_birth_province_id') ? document.getElementById('b_birth_province_id').value || null : null,
    b_birth_district_id: document.getElementById('b_birth_district_id') ? document.getElementById('b_birth_district_id').value || null : null,
    b_birth_taseng_id: document.getElementById('b_birth_taseng_id') ? document.getElementById('b_birth_taseng_id').value || null : null,
    b_birth_village_id: document.getElementById('b_birth_village_id') ? document.getElementById('b_birth_village_id').value || null : null
  };
  document.querySelectorAll('#docFields [name^="field_"]').forEach(el => { data[el.name] = el.value; });
  $.ajax({
    url: '../insert/insert_edoc_document.php',
    method: 'POST',
    data,
    headers: {'X-Requested-With': 'XMLHttpRequest'},
    success: function(res){
      const r = parseJson(res);
      if (r && r.status === 'success') {
        Swal.fire('ສຳເລັດ', r.message, 'success').then(() => { window.location.href='list_edoc_document.php?status=' + (mode==='draft'?'DRAFT':'PENDING_APPROVAL'); });
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
  fillSelect('province_id', PROVINCES, 'ເລືອກແຂວງ');
  fillSelect('birth_province_id', PROVINCES, 'ເລືອກແຂວງ');
  // Buyer selects
  fillSelect('b_nationality', LOOKUP.country, 'ເລືອກສັນຊາດ');
  fillSelect('b_origin', LOOKUP.country, 'ເລືອກເຊື້ອຊາດ');
  fillSelect('b_ethnicity', LOOKUP.ethnicity, 'ເລືອກຊົນເຜົ່າ');
  fillSelect('b_religion', LOOKUP.religion, 'ເລືອກສາສະໜາ');
  fillSelect('b_occupation', LOOKUP.occupation, 'ເລືອກອາຊີບ');
  fillSelect('b_province_id', PROVINCES, 'ເລືອກແຂວງ');
  fillSelect('b_birth_province_id', PROVINCES, 'ເລືອກແຂວງ');
  if (document.getElementById('nationality')) document.getElementById('nationality').value = 'LA';
  if (document.getElementById('origin')) document.getElementById('origin').value = 'LA';
  if (document.getElementById('b_nationality')) document.getElementById('b_nationality').value = 'LA';
  if (document.getElementById('b_origin')) document.getElementById('b_origin').value = 'LA';
  if (EDIT_DATA) {
    if (EDIT_DATA.id_card) document.getElementById('nid').value = EDIT_DATA.id_card;
    fillPerson(EDIT_DATA);
    if (document.getElementById('title')) document.getElementById('title').value = EDIT_DATA.title || '';
    document.getElementById('nidMsg').textContent = 'ແກ້ໄຂຮ່າງ: ' + (EDIT_DATA.first_name || '') + ' ' + (EDIT_DATA.last_name || '');
    // buyer prefill on edit (buyer loaded as a citizen via buyer_id)
    if (EDIT_DATA.buyer) {
      const bc = EDIT_DATA.buyer;
      if (bc.id_card) document.getElementById('b_nid').value = bc.id_card;
      fillBuyer(bc);
      if (bc.title) document.getElementById('b_title').value = bc.title;
      document.getElementById('b_nidMsg').textContent = '<?= $party2Label ?>: ' + (bc.first_name || '') + ' ' + (bc.last_name || '');
    }
  }
  loadDocFields();
});
</script>
<?php include '../components/layout_footer.php'; ?>
