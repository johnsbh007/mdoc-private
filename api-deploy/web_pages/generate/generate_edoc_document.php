<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require_once '../connect.php';
require_once '../edoc_auth.php';
// Generating the issued document is a deliverable/write action — gate on sign capability
edoc_require_cap('can_sign', 'ສ້າງ/ດາວໂຫລດເອກະສານ');

$doc_id = (int)($_GET['doc_id'] ?? 0);
if ($doc_id <= 0) { die('Missing doc_id'); }

// ── load doc + citizen + residence data ──────────────────────────────
$d = $conn->prepare("SELECT d.*, c.*, di.doc_type_id, v.name_lo AS village_name,
                            f.name_lo AS taseng_name, g.name_lo AS district_name,
                            h.name_lo AS province_name
                     FROM edoc_document d
                     JOIN edoc_citizen c ON d.citizen_id = c.id
                     JOIN edoc_doc_item di ON d.doc_item_id = di.id
                     LEFT JOIN edoc_village v ON c.village_id = v.id
                     LEFT JOIN edoc_taseng f ON c.taseng_id = f.id
                     LEFT JOIN edoc_district g ON c.district_id = g.id
                     LEFT JOIN edoc_province h ON c.province_id = h.id
                     WHERE d.id = ?");
$d->execute([$doc_id]);
$row = $d->fetch(PDO::FETCH_ASSOC);
if (!$row) { die('Document not found'); }

$res = null;
$rs = $conn->prepare("SELECT * FROM edoc_dc_residence WHERE doc_id = ?");
$rs->execute([$doc_id]);
$res = $rs->fetch(PDO::FETCH_ASSOC);

// ── generic EAV field values (edoc_form_data joined to field names) ──
$eav = [];
$ev = $conn->prepare("SELECT f.field_name, fd.field_value
                      FROM edoc_form_data fd
                      JOIN edoc_doc_field f ON f.id = fd.field_id
                      WHERE fd.doc_id = ?");
$ev->execute([$doc_id]);
foreach ($ev->fetchAll(PDO::FETCH_ASSOC) as $r) { $eav[$r['field_name']] = $r['field_value']; }

// ── resolve lookup codes → Lao names (save code, show name) ─────────
function resolveName($conn, $table, $code) {
  if ($code === '' || $code === null) return '';
  $st = $conn->prepare("SELECT name_lo FROM $table WHERE code = ? LIMIT 1");
  $st->execute([$code]);
  $n = $st->fetchColumn();
  return $n !== false ? $n : $code; // fallback: legacy free-text value
}
$originName     = resolveName($conn, 'edoc_country',    $row['origin']);
$nationalityName= resolveName($conn, 'edoc_country',    $row['nationality']);
$ethnicityName  = resolveName($conn, 'edoc_ethnicity',  $row['ethnicity']);
$religionName   = resolveName($conn, 'edoc_religion',   $row['religion']);
$occupationName = resolveName($conn, 'edoc_occupation', $row['occupation']);

// resolve geo names: prefer ID joins, fall back to legacy free-text columns
function geoName($conn, $table, $id, $fallback) {
  if ($id) {
    $st = $conn->prepare("SELECT name_lo FROM $table WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $n = $st->fetchColumn();
    if ($n !== false) return $n;
  }
  return $fallback ?: '';
}
$birthVillageName  = geoName($conn, 'edoc_village',  $row['birth_village_id']  ?? null, $row['birth_village']);
$birthTasengName   = geoName($conn, 'edoc_taseng',   $row['birth_taseng_id']   ?? null, '');
$birthDistrictName = geoName($conn, 'edoc_district', $row['birth_district_id'] ?? null, $row['birth_district']);
$birthProvinceName = geoName($conn, 'edoc_province', $row['birth_province_id'] ?? null, $row['birth_province']);
// parent address: structured geo from new fields
function geoNameId($conn, $table, $id) {
  if ($id) {
    $st = $conn->prepare("SELECT name_lo FROM $table WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $n = $st->fetchColumn();
    if ($n !== false) return $n;
  }
  return '';
}
$parentUnit      = $res['parents_unit_no'] ?? '';
$parentVillage   = geoNameId($conn, 'edoc_village',   $res['parents_village_id']   ?? null);
$parentTaseng    = geoNameId($conn, 'edoc_taseng',    $res['parents_taseng_id']    ?? null);
$parentDistrict  = geoNameId($conn, 'edoc_district',  $res['parents_district_id']  ?? null);
$parentProvince  = geoNameId($conn, 'edoc_province',  $res['parents_province_id']  ?? null);

function fmtDate($v) { return $v ? date('d/m/Y', strtotime($v)) : ''; }

$title = ($row['gender'] === 'M') ? 'ທ້າວ' : (($row['gender'] === 'F') ? 'ນາງ' : '');
$today = date('d/m/Y');

// ── open template copy (choose by doc type) ─────────────────────────
$template = ($row['doc_type_id'] == 14)
    ? '/var/www/html/edoc/Doc_files/31 ໃບຢັ້ງຢືນຊື້-ຂາຍ ທີ່ດິນ.docx'
    : '/var/www/html/edoc/Doc_files/32 ໃບຢັ້ງຢືນທີ່ຢູ່ (1).docx';
if (!file_exists($template)) { die('Template not found'); }
$tmp = tempnam(sys_get_temp_dir(), 'cert_') . '.docx';
copy($template, $tmp);

$zip = new ZipArchive();
if ($zip->open($tmp) !== true) { die('Cannot open template'); }
$xml = $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === false) { die('document.xml missing'); }

// ── run-aware fill on word/document.xml ─────────────────────────────
$dom = new DOMDocument();
$dom->loadXML($xml);
$ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

function wRuns($p) {
  $out = [];
  foreach ($p->childNodes as $c) {
    if ($c->nodeType === XML_ELEMENT_NODE && $c->localName === 'r') $out[] = $c;
  }
  return $out;
}
function runText($r) {
  $s = '';
  foreach ($r->childNodes as $c) if ($c->localName === 't') $s .= $c->textContent;
  return $s;
}
function setRunText($r, $text) {
  $first = null;
  foreach ($r->childNodes as $c) {
    if ($c->localName === 't') { if ($first === null) $first = $c; else $c->textContent = ''; }
  }
  if ($first !== null) $first->textContent = $text;
}
// splice NEW into the paragraph at absolute byte index $absIdx, replacing $OLen bytes
function spliceAt($p, $absIdx, $OLen, $NEW) {
  $runs = wRuns($p);
  $pos = 0; $start = null; $end = null; $ls = 0; $le = 0;
  foreach ($runs as $i => $r) {
    $t = runText($r); $rlen = strlen($t);
    if ($start === null && $pos + $rlen > $absIdx) { $start = $i; $ls = $absIdx - $pos; }
    if ($pos + $rlen >= $absIdx + $OLen) { $end = $i; $le = $absIdx + $OLen - $pos; break; }
    $pos += $rlen;
  }
  if ($start === null) return false;
  if ($start === $end) {
    $t = runText($runs[$start]);
    setRunText($runs[$start], substr($t, 0, $ls) . $NEW . substr($t, $le));
  } else {
    $t = runText($runs[$start]);
    setRunText($runs[$start], substr($t, 0, $ls) . $NEW);
    for ($i = $start + 1; $i < $end; $i++) setRunText($runs[$i], '');
    $te = runText($runs[$end]);
    setRunText($runs[$end], substr($te, $le));
  }
  return true;
}
function replacePhrase($p, $OLD, $NEW) {
  $full = '';
  foreach (wRuns($p) as $r) $full .= runText($r);
  $idx = strpos($full, $OLD);
  if ($idx === false) return false;
  return spliceAt($p, $idx, strlen($OLD), $NEW);
}
// fill the dots that follow $label in paragraph $p (splice at exact position)
function fillDots($p, $label, $value) {
  if ($value === '') return;
  $full = '';
  foreach (wRuns($p) as $r) $full .= runText($r);
  $pos = strpos($full, $label);
  if ($pos === false) return;
  $len = strlen($full);
  $ws = $pos + strlen($label);
  while ($ws < $len && $full[$ws] === ' ') $ws++;
  $e = $ws;
  while ($e < $len && $full[$e] === '.') $e++;
  if ($e > $ws) {
    $ins = (trim(substr($full, $e)) === '') ? ' ' . $value : ' ' . $value . ', ';
    spliceAt($p, $ws, $e - $ws, $ins);
  }
}

// Name line: title+first_name, then a comma, then the ນາມສະກຸນ label + surname. No dots.
function fillNameLine($p, $titleFirst, $surname) {
  replacePhrase($p, 'ທ້າວ ຫຼື ນາງ', $titleFirst);
  $full = ''; foreach (wRuns($p) as $r) $full .= runText($r);
  $pos = strpos($full, 'ນາມສະກຸນ');
  if ($pos === false) return;
  $e = $pos;
  while ($e > 0 && ($full[$e - 1] === '.' || $full[$e - 1] === ' ')) $e--;
  spliceAt($p, $e, $pos - $e, ', ');
  $full = ''; foreach (wRuns($p) as $r) $full .= runText($r);
  $p2 = strpos($full, 'ນາມສະກຸນ');
  $ls = $p2 + strlen('ນາມສະກຸນ');
  $len = strlen($full); $ws = $ls;
  while ($ws < $len && $full[$ws] === ' ') $ws++;
  $ee = $ws; while ($ee < $len && $full[$ee] === '.') $ee++;
  $ins = ($surname === '') ? ' ' : ' ' . $surname;
  if ($ee > $ws) spliceAt($p, $ws, $ee - $ws, $ins);
  else spliceAt($p, $ws, 0, $ins);
}

// Title for the name line: keep the original "ທ້າວ ຫຼື ນາງ" wording if gender unknown.
$nameTitle = ($title !== '') ? $title : 'ທ້າວ ຫຼື ນາງ';

// ── ops per doc type ────────────────────────────────────────────────
// Type 12 = ໃບຢັ້ງຢືນຊື້-ຂາຍ ທີ່ດິນ (land sell-purchase letter). The applicant is the
// SELLER (citizen record); witnesses/land/borders/buyer come from the EAV fields.
$opsByAnchor = [];
if ((int)$row['doc_type_id'] === 14) {
    $eavGet = function($k) use ($eav) { return trim((string)($eav[$k] ?? '')); };
    $opsByAnchor = [
            '/ຄປຕ' => [['dots', 'ເລກທີ່', $row['doc_no']]],
            'PREFIXວັນທີ' => [['dots', 'ວັນທີ', $today]],
            // line 8-10: three witnesses — each line starts with "1.ທ່ານ…" / "2.ທ່ານ…" / "3.ທ່ານ…"
        '1.ທ່ານ' => [
            ['dots', 'ທ່ານ', $eavGet('witness1_name')],
            ['dots', 'ອາຍຸ', $eavGet('witness1_age')],
            ['dots', 'ອາຊີບ', $eavGet('witness1_occupation')],
            ['dots', 'ປະຈຸບັນຢູ່ບ້ານ', $eavGet('witness1_village')],
        ],
        '2.ທ່ານ' => [
            ['dots', 'ທ່ານ', $eavGet('witness2_name')],
            ['dots', 'ອາຍຸ', $eavGet('witness2_age')],
            ['dots', 'ອາຊີບ', $eavGet('witness2_occupation')],
            ['dots', 'ປະຈຸບັນຢູ່ບ້ານ', $eavGet('witness2_village')],
        ],
        '3.ທ່ານ' => [
            ['dots', 'ທ່ານ', $eavGet('witness3_name')],
            ['dots', 'ອາຍຸ', $eavGet('witness3_age')],
            ['dots', 'ອາຊີບ', $eavGet('witness3_occupation')],
            ['dots', 'ປະຈຸບັນຢູ່ບ້ານ', $eavGet('witness3_village')],
        ],
        // seller = applicant (citizen)
        'ຂ້າພະເຈົ້າ' => [
            ['phrase', 'ທ້າວ/ນາງ', $nameTitle],
            ['dots', 'ອາຍຸ', $row['age'] ?? ($row['dob'] ? floor((time()-strtotime($row['dob']))/31536000) : '')],
            ['dots', 'ສັນຊາດ', $nationalityName],
            ['dots', 'ປະຈຸບັນຢູ່ໜ່ວຍ', $row['unit_no']],
            ['dots', 'ບ້ານ', $row['village_name']],
            ['dots', 'ເມືອງ', $row['district_name'] ?? ''],
            ['dots', 'ແຂວງ', $row['province_name'] ?? ''],
            ['dots', 'ຂື້ນສຳມະໂນຄົວເລກທີ', $eavGet('seller_family_book')],
            ['dots', 'ບັດປະຈຳຕົວເລກທີ', $row['id_card']],
            ['dots', 'ເບີໂທ', $row['phone']],
        ],
        // land
        'ໄດ້ເຫັນດີຕົກລົງຂາຍ' => [
            ['dots', 'ຈໍານວນ', $eavGet('land_plots')],
            ['dots', 'ເປັນຈໍານວນເງິນ', number_format((float)$eavGet('land_amount'))],
            ['dots', 'ເຊິ່ງມີເນື້ອທີ່', $eavGet('land_area')],
        ],
        // borders (4 lines, each with neighbor + meters)
        'ທິດເໜືອ' => [['dots', 'ທິດເໜືອ ຕິດກັບ', $eavGet('border_north_neighbor')], ['dots', 'ໄລຍະ', $eavGet('border_north_meters')]],
        'ທິດໃຕ້' => [['dots', 'ທິດໃຕ້ ຕິດກັບ', $eavGet('border_south_neighbor')], ['dots', 'ໄລຍະ', $eavGet('border_south_meters')]],
        'ທິດຕາເວັນອອກ' => [['dots', 'ທິດຕາເວັນອອກ ຕິດກັບ', $eavGet('border_east_neighbor')], ['dots', 'ໄລຍະ', $eavGet('border_east_meters')]],
        'ທິດຕາເວັນຕົກ' => [['dots', 'ທິດຕາເວັນຕົກ ຕິດກັບ', $eavGet('border_west_neighbor')], ['dots', 'ໄລຍະ', $eavGet('border_west_meters')]],
        // buyer
        'ຂາຍໃຫ້ແກ່' => [
            ['dots', 'ຂາຍໃຫ້ແກ່ທ້າວ/ນາງ', $eavGet('buyer_name')],
            ['dots', 'ອາຍຸ', $eavGet('buyer_age')],
            ['dots', 'ສັນຊາດ', $eavGet('buyer_nationality')],
            ['dots', 'ຊົນເຜົ່າ', $eavGet('buyer_ethnicity')],
            ['dots', 'ສາສະໜາ', $eavGet('buyer_religion')],
            ['dots', 'ອາຊີບ', $eavGet('buyer_occupation')],
            ['dots', 'ປະຈຸບັນຢູ່ໜ່ວຍ', $eavGet('buyer_unit')],
            ['dots', 'ບ້ານ', $eavGet('buyer_village')],
            ['dots', 'ເມືອງ', $eavGet('buyer_district')],
            ['dots', 'ແຂວງ', $eavGet('buyer_province')],
            ['dots', 'ຂື້ນສຳມະໂນຄົວເລກທີ', $eavGet('buyer_family_book')],
            ['dots', 'ບັດປະຈຳຕົວເລກທີ', $eavGet('buyer_id_card')],
            ['dots', 'ເບີໂທ', $eavGet('buyer_phone')],
        ],
        // final ownership statement (buyer name again)
        'ເປັນເຈົ້າຂອງ' => [['dots', 'ທ້າວ/ນາງ', $eavGet('buyer_name')]],
    ];
} else {
    // residence (type 1) — original fill table
    $opsByAnchor = [
        '/ຄປຕ' => [['dots', 'ເລກທີ', $row['doc_no']]],
        'PREFIXວັນທີ' => [['dots', 'ວັນທີ', $today]],
        'ທ້າວ ຫຼື ນາງ' => [['name', trim($nameTitle . ' ' . $row['first_name']), $row['last_name']]],
        'ວັນ,ເດືອນ,ປີເກີດ' => [['dots', 'ວັນ,ເດືອນ,ປີເກີດ', fmtDate($row['dob'])], ['dots', 'ບ້ານເກີດ', $birthVillageName], ['dots', 'ຕາແສງ', $birthTasengName], ['dots', 'ເມືອງ', $birthDistrictName]],
        'ເຊື້ອຊາດ' => [['dots', 'ແຂວງ', $birthProvinceName], ['dots', 'ເຊື້ອຊາດ', $originName], ['dots', 'ສັນຊາດ', $nationalityName], ['dots', 'ສາສະໜາ', $religionName], ['dots', 'ຊົນເຜົ່າ', $ethnicityName], ['dots', 'ອາຊີບ', $occupationName], ['dots', 'ບັດປະຈໍາຕົວເລກທີ', $row['id_card']], ['dots', 'ລົງວັນທີ', fmtDate($row['id_card_issue_date'])]],
        'ເຮືອນເລກທີ' => [['dots', 'ເຮືອນເລກທີ', $row['house_no']], ['dots', 'ໜ່ວຍທີ', $row['unit_no']], ['dots', 'ຕາແສງ', $row['taseng_name'] ?? ''], ['dots', 'ບ້ານ', $row['village_name']], ['dots', 'ເມືອງ', $row['district_name'] ?? ''], ['dots', 'ແຂວງ', $row['province_name'] ?? '']],
        'ສໍາມະໂນຄົວ' => [['dots', 'ຂື້ນສໍາມະໂນຄົວເລກທີ', $res['household_book_no'] ?? ''], ['dots', 'ລົງວັນທີ', fmtDate($res['household_book_date'] ?? '')]],
        'ເບີໂທລະສັບ' => [['dots', 'ເບີໂທລະສັບ', $row['phone']], ['dots', 'ເປັນລູກຂອງທ້າວ', $res['father_name'] ?? '']],
        'ແລະ ນາງ' => [['dots', 'ແລະ ນາງ', $res['mother_name'] ?? ''], ['dots', 'ໜ່ວຍ', $parentUnit], ['dots', 'ຕາແສງ', $parentTaseng], ['dots', 'ບ້ານ', $parentVillage]],
        'ແຂວງ' => [['dots', 'ເມືອງ', $parentDistrict], ['dots', 'ແຂວງ', $parentProvince]],
    ];
}

$paras = $dom->getElementsByTagNameNS($ns, 'p');
foreach ($paras as $p) {
  $full = '';
  foreach (wRuns($p) as $r) $full .= runText($r);
  $trimmed = ltrim($full);
  foreach ($opsByAnchor as $anchor => $ops) {
    if (strpos($anchor, 'PREFIX') === 0) {
      if (strpos($trimmed, substr($anchor, 6)) !== 0) continue; // line-start match only
    } else {
      if (strpos($full, $anchor) === false) continue;
    }
    foreach ($ops as $op) {
      if ($op[0] === 'dots')   fillDots($p, $op[1], $op[2]);
      else if ($op[0] === 'phrase') replacePhrase($p, $op[1], $op[2]);
      else if ($op[0] === 'name') fillNameLine($p, $op[1], $op[2]);
    }
    break; // one anchor per paragraph
  }
}

// ── cleanup: normalize the filled text artifacts from fillDots (' value, ') ──
// Remove the separator comma that lands right before /ຄປຕ (doc-ref doc_no),
// then collapse multiple spaces inside every w:t text node (matches the preview's pvClean).
foreach ($paras as $p) {
  $full = '';
  foreach (wRuns($p) as $r) $full .= runText($r);
  if (strpos($full, '/ຄປຕ') !== false) {
    $c2 = strpos($full, ',');
    $k2 = strpos($full, '/ຄປຕ');
    if ($c2 !== false && $k2 !== false && $k2 - $c2 >= 1 && $k2 - $c2 <= 2) spliceAt($p, $c2, $k2 - $c2, '');
  }
}
// collapse multiple spaces inside every w:t text node
foreach ($paras as $p) {
  foreach (wRuns($p) as $r) {
    foreach ($r->childNodes as $c) {
      if ($c->nodeType === XML_ELEMENT_NODE && $c->localName === 't') {
        $c->textContent = preg_replace('/\s{2,}/', ' ', $c->textContent);
      }
    }
  }
}

// ── save back into the docx ─────────────────────────────────────────
$newXml = $dom->saveXML();
$zip = new ZipArchive();
if ($zip->open($tmp) !== true) { die('Cannot rewrite template'); }
$zip->addFromString('word/document.xml', $newXml);
$zip->close();

// ── stream as download, or store to edoc_file_store (save=1) ───────
$fname = (($row['doc_type_id'] == 14) ? 'ໃບຢັ້ງຢືນຊື້-ຂາຍ ທີ່ດິນ_' : 'ໃບຢັ້ງຢືນທີ່ຢູ່_') . ($row['doc_no'] ?? $doc_id) . '.docx';
$ascii = (($row['doc_type_id'] == 14) ? 'land_sale_' : 'residence_cert_') . ($row['doc_no'] ?? $doc_id) . '.docx';

$save = (int)($_GET['save'] ?? 0);
if ($save === 1) {
    // Store the issued document under files/issued/, record it in edoc_file_store
    // NOTE: this file lives at /edoc/generate/, so the app root is dirname(__DIR__)
    $appRoot = dirname(__DIR__);
    $dir = $appRoot . '/files/issued';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $relDir  = 'files/issued';
    $stored  = $relDir . '/' . $ascii;
    $absDest = $appRoot . '/' . $stored;
    if (!copy($tmp, $absDest)) { http_response_code(500); die('Could not store issued document'); }
    @chmod($absDest, 0664);

    // next version number for this doc
    $v = $conn->prepare("SELECT COALESCE(MAX(version),0)+1 FROM edoc_file_store WHERE doc_id=?");
    $v->execute([$doc_id]);
    $version = (int)$v->fetchColumn();

    $ins = $conn->prepare("INSERT INTO edoc_file_store (doc_id, file_path, signed_by, version) VALUES (?,?,?,?)");
    $ins->execute([$doc_id, $stored, ($row['approved_by'] ?? null) ?: null, $version]);

    // JSON response so the list can react
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status'=>'success','message'=>'ເອກະສານຖືກສ້າງ ແລະ ບັນທຶກແລ້ວ (v'.$version.')','file'=>$stored,'version'=>$version]);
    unlink($tmp);
    exit;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($fname));
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
unlink($tmp);
exit;
