<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require_once '../connect.php';

$doc_id = (int)($_GET['doc_id'] ?? 0);
if ($doc_id <= 0) { die('Missing doc_id'); }

// ── load doc + citizen + residence data ──────────────────────────────
$d = $conn->prepare("SELECT d.*, c.*, v.name_lo AS village_name
                     FROM edoc_document d
                     JOIN edoc_citizen c ON d.citizen_id = c.id
                     LEFT JOIN edoc_village v ON c.village_id = v.id
                     WHERE d.id = ?");
$d->execute([$doc_id]);
$row = $d->fetch(PDO::FETCH_ASSOC);
if (!$row) { die('Document not found'); }

$res = null;
$rs = $conn->prepare("SELECT * FROM edoc_dc_residence WHERE doc_id = ?");
$rs->execute([$doc_id]);
$res = $rs->fetch(PDO::FETCH_ASSOC);

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

// ── open template copy ───────────────────────────────────────────────
$template = '/var/www/html/edoc/Doc_files/32 ໃບຢັ້ງຢືນທີ່ຢູ່ (1).docx';
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

// Title for the name line: keep the original "ທ້າວ ຫຼື ນາງ" wording if gender unknown.
$nameTitle = ($title !== '') ? $title : 'ທ້າວ ຫຼື ນາງ';
// ops per paragraph (matched by unique anchor substring, in document order)
$opsByAnchor = [
  '/ຄປຕ' => [['dots', 'ເລກທີ', $row['doc_no']]],
  'PREFIXວັນທີ' => [['dots', 'ວັນທີ', $today]],
  // Name line: put title+first_name in ONE phrase replacement.
  'ທ້າວ ຫຼື ນາງ' => [['phrase', 'ທ້າວ ຫຼື ນາງ', trim($nameTitle . ' ' . $row['first_name'])], ['dots', 'ນາມສະກຸນ', $row['last_name']]],
  'ວັນ,ເດືອນ,ປີເກີດ' => [['dots', 'ວັນ,ເດືອນ,ປີເກີດ', fmtDate($row['dob'])], ['dots', 'ບ້ານເກີດ', $birthVillageName], ['dots', 'ຕາແສງ', $birthTasengName], ['dots', 'ເມືອງ', $birthDistrictName]],
  'ເຊື້ອຊາດ' => [['dots', 'ແຂວງ', $birthProvinceName], ['dots', 'ເຊື້ອຊາດ', $originName], ['dots', 'ສັນຊາດ', $nationalityName], ['dots', 'ສາສະໜາ', $religionName], ['dots', 'ຊົນເຜົ່າ', $ethnicityName], ['dots', 'ອາຊີບ', $occupationName], ['dots', 'ບັດປະຈໍາຕົວເລກທີ', $row['id_card']], ['dots', 'ລົງວັນທີ', fmtDate($row['id_card_issue_date'])]],
  'ເຮືອນເລກທີ' => [['dots', 'ເຮືອນເລກທີ', $row['house_no']], ['dots', 'ໜ່ວຍທີ', $row['unit_no']], ['dots', 'ຕາແສງ', $res['taseng_name'] ?? ''], ['dots', 'ບ້ານ', $row['village_name']], ['dots', 'ເມືອງ', $res['district_name'] ?? ''], ['dots', 'ແຂວງ', $res['province_name'] ?? '']],
  'ສໍາມະໂນຄົວ' => [['dots', 'ຂື້ນສໍາມະໂນຄົວເລກທີ', $res['household_book_no'] ?? ''], ['dots', 'ລົງວັນທີ', fmtDate($res['household_book_date'] ?? '')]],
  'ເບີໂທລະສັບ' => [['dots', 'ເບີໂທລະສັບ', $row['phone']], ['dots', 'ເປັນລູກຂອງທ້າວ', $res['father_name'] ?? '']],
  'ແລະ ນາງ' => [['dots', 'ແລະ ນາງ', $res['mother_name'] ?? ''], ['dots', 'ໜ່ວຍ', $parentUnit], ['dots', 'ຕາແສງ', $parentTaseng], ['dots', 'ບ້ານ', $parentVillage], ['dots', 'ເມືອງ', $parentDistrict], ['dots', 'ແຂວງ', $parentProvince]],
];

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

// ── stream as download ──────────────────────────────────────────────
$fname = 'ໃບຢັ້ງຢືນທີ່ຢູ່_' . ($row['doc_no'] ?? $doc_id) . '.docx';
$ascii = 'residence_cert_' . ($row['doc_no'] ?? $doc_id) . '.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($fname));
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
unlink($tmp);
exit;
