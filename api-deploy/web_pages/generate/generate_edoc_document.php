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
$d = $conn->prepare("SELECT d.*, c.*, di.doc_type_id, di.id AS doc_item_id, v.name_lo AS village_name,
                            f.name_lo AS taseng_name, g.name_lo AS district_name,
                            h.name_lo AS province_name,
                            b.id AS buyer_citizen_id, b.title AS buyer_title, b.first_name AS buyer_first_name,
                            b.last_name AS buyer_last_name, b.dob AS buyer_dob, b.id_card AS buyer_id_card,
                            b.phone AS buyer_phone, b.nationality AS buyer_nationality, b.origin AS buyer_origin,
                            b.ethnicity AS buyer_ethnicity, b.religion AS buyer_religion,
                            b.occupation AS buyer_occupation, b.unit_no AS buyer_unit_no,
                            bv.name_lo AS buyer_village_name, bt.name_lo AS buyer_taseng_name,
                            bd.name_lo AS buyer_district_name, bp.name_lo AS buyer_province_name
                     FROM edoc_document d
                     JOIN edoc_citizen c ON d.citizen_id = c.id
                     JOIN edoc_doc_item di ON d.doc_item_id = di.id
                     LEFT JOIN edoc_citizen b ON d.buyer_id = b.id
                     LEFT JOIN edoc_village v ON c.village_id = v.id
                     LEFT JOIN edoc_taseng f ON c.taseng_id = f.id
                     LEFT JOIN edoc_district g ON c.district_id = g.id
                     LEFT JOIN edoc_province h ON c.province_id = h.id
                                          LEFT JOIN edoc_village bv ON b.village_id = bv.id
                                          LEFT JOIN edoc_taseng bt ON b.taseng_id = bt.id
                                          LEFT JOIN edoc_district bd ON b.district_id = bd.id
                                          LEFT JOIN edoc_province bp ON b.province_id = bp.id
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
$template = ($row['doc_type_id'] == 5)
    ? '/var/www/html/edoc/Doc_files/05-26-40 ບົດບັນທຶກການຢ່າຮ້າງ.docx'
    : (($row['doc_type_id'] == 4)
    ? '/var/www/html/edoc/Doc_files/04-04 ໃບແຈ້ງເສຍຫາຍ.docx'
    : (($row['doc_type_id'] == 2)
    ? '/var/www/html/edoc/Doc_files/02-20-41 ໃບຄຳຮ້ອງ ຂໍຈົດທະບຽນແຕ່ງດອງ.docx'
    : (($row['doc_type_id'] == 14)
    ? '/var/www/html/edoc/Doc_files/31 ໃບຢັ້ງຢືນຊື້-ຂາຍ ທີ່ດິນ.docx'
    : ($row['doc_type_id'] == 6
        ? (((int)$row['doc_item_id'] === 53)
            ? '/var/www/html/edoc/Doc_files/06-31-03 ສັນຍາຊື້-ຂາຍ ກຳມະສິດທີ່ດິນ.docx'
            : (((int)$row['doc_item_id'] === 30)
                ? '/var/www/html/edoc/Doc_files/06-30-21 ໃບສັນຍາເຊົ່າທີ່ດິນ ແລະ ເຮືອນ.docx'
                : '/var/www/html/edoc/Doc_files/06-28-02 ສັນຍາກູ້ຢືມເງິນ.docx'))
        : (((int)$row['doc_item_id'] === 5)
            ? '/var/www/html/edoc/Doc_files/01-05-ໃບຢັ້ງຢືນສາຍຍາດ.docx'
            : (((int)$row['doc_item_id'] === 8)
                            ? (($row['template_variant'] ?? '') === 'short'
                                ? '/var/www/html/edoc/Doc_files/01-08-ປະຫວັດຫຍໍ້.docx'
                                : '/var/www/html/edoc/Doc_files/01-08-08 ຊີວະປະຫວັດຫຍໍ້ຫຼາຍໜ້າ.docx')
                            : (((int)$row['doc_item_id'] === 54)
                                ? '/var/www/html/edoc/Doc_files/01-28-22 ໃບຍົກຍ້າຍ.docx'
                                : (((int)$row['doc_item_id'] === 55)
                                    ? '/var/www/html/edoc/Doc_files/01-29-29 ໃບຢັ້ງຢືນການເສຍຫາຍ.docx'
                                    : (((int)$row['doc_item_id'] === 56)
                                        ? '/var/www/html/edoc/Doc_files/01-30 ໃບຢັ້ງຢືນຄວາມເປັນໂສດ.docx'
                                        : (((int)$row['doc_item_id'] === 57)
                                            ? '/var/www/html/edoc/Doc_files/01-31-35 ໃບຢັ້ງຢືນລູກທາງ (ລູກຊູ້).docx'
                                            : '/var/www/html/edoc/Doc_files/32 ໃບຢັ້ງຢືນທີ່ຢູ່ (1).docx'))))))))));
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
// true if the text at $full[$i] starts a dot-fill char ('.' or the 3-byte '…' ellipsis).
// Returns the byte length consumed (1 for '.', 3 for '…'), 0 if not a dot char.
function dotLen($full, $i) {
  $b = $full[$i];
  if ($b === '.') return 1;
  if ($b === "\xE2" && substr($full, $i, 3) === '…') return 3; // U+2026
  return 0;
}

// fill the dots that follow $label in paragraph $p (splice at exact position).
// Returns TRUE when the row is fillable (label found AND dots after it) — the
// occurrence counter advances on fillable rows even when the value is empty, so
// blank rows still consume their slot. Returns FALSE for heading lines that match
// a PREFIX anchor but have no dots (they must NOT steal an occurrence slot).
function fillDots($p, $label, $value) {
  $full = '';
  foreach (wRuns($p) as $r) $full .= runText($r);
  $pos = strpos($full, $label);
  if ($pos === false) return false;
  $len = strlen($full);
  $ws = $pos + strlen($label);
  while ($ws < $len && $full[$ws] === ' ') $ws++;
  $e = $ws;
  while ($e < $len && ($dl = dotLen($full, $e)) > 0) $e += $dl;
  if ($e > $ws) {
    if ($value !== '') {
      $ins = (trim(substr($full, $e)) === '') ? ' ' . $value : ' ' . $value . ', ';
      spliceAt($p, $ws, $e - $ws, $ins);
    }
    return true;
  }
  return false;
}

// fill a span of dots (and '/' separators, and '…' ellipsis) that follow $label —
// used for the ".........../........../..........." date / amount placeholders.
// Returns TRUE when the row is fillable (label found AND dots after it).
function fillDotSpan($p, $label, $value) {
  $full = '';
  foreach (wRuns($p) as $r) $full .= runText($r);
  $pos = strpos($full, $label);
  if ($pos === false) return false;
  $len = strlen($full);
  $ws = $pos + strlen($label);
  while ($ws < $len && $full[$ws] === ' ') $ws++;
  $e = $ws;
  while ($e < $len) {
    $dl = dotLen($full, $e);
    if ($dl > 0) { $e += $dl; continue; }
    if ($full[$e] === '/') { $e++; continue; }
    break;
  }
  if ($e > $ws) {
    if ($value !== '') {
      $ins = (trim(substr($full, $e)) === '') ? ' ' . $value : ' ' . $value . ', ';
      spliceAt($p, $ws, $e - $ws, $ins);
    }
    return true;
  }
  return false;
}

// fill dots after the Nth occurrence of $label in paragraph $p (1-based $n).
// Used when a label repeats inside one paragraph (e.g. ເປັນຕົວໜັງສື x2).
function fillDotsN($p, $label, $value, $n) {
  $full = '';
  foreach (wRuns($p) as $r) $full .= runText($r);
  $pos = 0; $found = 0;
  while (($pos = strpos($full, $label, $pos)) !== false) {
    $found++;
    if ($found === $n) break;
    $pos += strlen($label);
  }
  if ($found < $n) return false;
  $len = strlen($full);
  $ws = $pos + strlen($label);
  while ($ws < $len && $full[$ws] === ' ') $ws++;
  $e = $ws;
  while ($e < $len && ($dl = dotLen($full, $e)) > 0) $e += $dl;
  if ($e > $ws) {
    if ($value !== '') {
      $ins = (trim(substr($full, $e)) === '') ? ' ' . $value : ' ' . $value . ', ';
      spliceAt($p, $ws, $e - $ws, $ins);
    }
    return true;
  }
  return false;
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
    // Seller name
    $sellerName = trim($nameTitle . ' ' . $row['first_name'] . ' ' . $row['last_name']);
    // Buyer age (computed from dob)
    $buyerAge = $row['buyer_dob'] ? floor((time()-strtotime($row['buyer_dob']))/31536000) : '';
    // Buyer name
    $buyerName = trim($row['buyer_title'] . ' ' . $row['buyer_first_name'] . ' ' . $row['buyer_last_name']);
    $opsByAnchor = [
            '/ຄປຕ' => [['dots', 'ເລກທີ່', $row['doc_no']]],
            'PREFIXວັນທີ' => [['dots', 'ວັນທີ', $today]],
            // witnesses (3 lines)
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
        // seller = applicant (citizen) — P10: name, age, nationality, unit
        'ຂ້າພະເຈົ້າ' => [
            ['dots', 'ຂ້າພະເຈົ້າ', $sellerName],
            ['dots', 'ອາຍຸ', $row['age'] ?? ($row['dob'] ? floor((time()-strtotime($row['dob']))/31536000) : '')],
            ['dots', 'ສັນຊາດ', $nationalityName],
            ['dots', 'ປະຈຸບັນຢູ່ໜ່ວຍ', $row['unit_no']],
        ],
        // seller continuation — P11: village, district, province, family book (1st occurrence)
        'PREFIXບ້ານ' => [
            [['dots', 'ບ້ານ', $row['village_name']],
             ['dots', 'ເມືອງ', $row['district_name'] ?? ''],
             ['dots', 'ແຂວງ', $row['province_name'] ?? ''],
             ['dots', 'ຂື້ນສຳມະໂນຄົວເລກທີ', $eavGet('seller_family_book')]],
            [['dots', 'ບ້ານ', $row['buyer_village_name'] ?? ''],
             ['dots', 'ເມືອງ', $row['buyer_district_name'] ?? ''],
             ['dots', 'ແຂວງ', $row['buyer_province_name'] ?? ''],
             ['dots', 'ຂື້ນສຳມະໂນຄົວເລກທີ', $eavGet('buyer_family_book')]],
        ],
        // seller continuation — P12: id card, phone (1st occurrence)
        'PREFIXລົງວັນທີ' => [
            [['dots', 'ບັດປະຈຳຕົວເລກທີ', $row['id_card']],
             ['dots', 'ເບີໂທ:', $row['phone']]],
            [['dots', 'ບັດປະຈຳຕົວເລກທີ', $row['buyer_id_card']],
             ['dots', 'ເບີໂທ:', $row['buyer_phone']]],
        ],
        // buyer (real citizen record via buyer_id) — P19: name, age
        'ຂາຍໃຫ້ແກ່' => [
            ['phrase', 'ຂາຍໃຫ້ແກ່ທ້າວ/ນາງ', $buyerName],
            ['dots', 'ອາຍຸ', $buyerAge],
        ],
        // buyer continuation — P20: nationality, ethnicity, religion, occupation, unit (unique)
        'PREFIXສັນຊາດ' => [
            ['dots', 'ສັນຊາດ', $row['buyer_nationality']],
            ['dots', 'ຊົນເຜົ່າ', $row['buyer_ethnicity']],
            ['dots', 'ສາສະໜາ', $row['buyer_religion']],
            ['dots', 'ອາຊີບ', $row['buyer_occupation']],
            ['dots', 'ປະຈຸບັນຢູ່ໜ່ວຍ', $row['buyer_unit_no']],
        ],
        // buyer continuation — P21: village, district, province, book (2nd occurrence via PREFIXບ້ານ above)
        // buyer continuation — P22: id card, phone (2nd occurrence via PREFIXລົງວັນທີ above)
        // final ownership statement — P24: buyer name (unique)
        'PREFIXທ້າວ/ນາງ' => [
            ['dots', 'ທ້າວ/ນາງ', $buyerName],
        ],
        // land
        'ໄດ້ເຫັນດີຕົກລົງຂາຍ' => [
            ['dots', 'ຈໍານວນ', $eavGet('land_plots')],
            ['dots', 'ເປັນຈໍານວນເງິນ', number_format((float)$eavGet('land_amount'))],
            ['dots', 'ເຊິ່ງມີເນື້ອທີ່', $eavGet('land_area')],
        ],
        // borders (4 lines)
        'ທິດເໜືອ' => [['dots', 'ທິດເໜືອ ຕິດກັບ', $eavGet('border_north_neighbor')], ['dots', 'ໄລຍະ', $eavGet('border_north_meters')]],
        'ທິດໃຕ້' => [['dots', 'ທິດໃຕ້ ຕິດກັບ', $eavGet('border_south_neighbor')], ['dots', 'ໄລຍະ', $eavGet('border_south_meters')]],
        'ທິດຕາເວັນອອກ' => [['dots', 'ທິດຕາເວັນອອກ ຕິດກັບ', $eavGet('border_east_neighbor')], ['dots', 'ໄລຍະ', $eavGet('border_east_meters')]],
        'ທິດຕາເວັນຕົກ' => [['dots', 'ທິດຕາເວັນຕົກ ຕິດກັບ', $eavGet('border_west_neighbor')], ['dots', 'ໄລຍະ', $eavGet('border_west_meters')]],
    ];
    } elseif ((int)$row['doc_type_id'] === 1 && (int)$row['doc_item_id'] === 5) {
        // ໃບຢັ້ງຢືນສາຍຍາດ (kinship certificate, item 5)
        // Applicant block from the citizen record; relatives from EAV fields.
        // Identical ຊື່/ຊື່ພໍ່/ຊື່ແມ່ rows appear twice (own family then spouse's family)
        // -> occurrence-aware PREFIX lists, in document order.
        $eavGet = function($k) use ($eav) { return trim((string)($eav[$k] ?? '')); };
        $applicantName = trim($nameTitle . ' ' . $row['first_name'] . ' ' . $row['last_name']);
        $opsByAnchor = [
            'PREFIXວັນທີ' => [['dots', 'ວັນທີ', $today]],
            // P7 applicant (line starts ຊື່ ແລະ ນາມສະກຸນຂອງເຈົ້າຂອງສິດ...)
            'ຊື່ ແລະ ນາມສະກຸນຂອງເຈົ້າຂອງສິດ' => [
                ['phrase', 'ຊື່ ແລະ ນາມສະກຸນຂອງເຈົ້າຂອງສິດນໍາໃຊ້ທີ່ດິນ', $applicantName],
                ['dots', 'ວັນ,ເດືອນ,ປີເກີດ', fmtDate($row['dob'])],
                ['dots', 'ສັນຊາດ', $nationalityName],
                ['dots', 'ອາຊີບ', $occupationName],
                ['dots', 'ມີບັດປະຈຳຕົວເລກທີ', $row['id_card']],
                ['dots', 'ລົງວັນທີ', fmtDate($row['id_card_issue_date'])],
                ['dots', 'ອອກໃຫ້ໂດຍ', $eavGet('kin_issued_by')],
                ['dots', 'ປະຈຸບັນຢູ່ໜ່ວຍ', $row['unit_no']],
                ['dots', 'ບ້ານ', $row['village_name']],
                ['dots', 'ເມືອງ', $row['district_name'] ?? ''],
                ['dots', 'ແຂວງ', $row['province_name'] ?? ''],
            ],
            // P9 father (own) / P16 father (spouse's) — occurrence 0,1
            'PREFIXຊື່ພໍ່' => [
                [['dots', 'ຊື່ພໍ່', $eavGet('father_name')], ['dots', 'ອາຍຸ', $eavGet('father_age')], ['dots', 'ອາຊີບ', $eavGet('father_occupation')], ['dots', 'ບ້ານ', $eavGet('father_village')]],
                [['dots', 'ຊື່ພໍ່', $eavGet('sp_father_name')], ['dots', 'ອາຍຸ', $eavGet('sp_father_age')], ['dots', 'ອາຊີບ', $eavGet('sp_father_occupation')], ['dots', 'ບ້ານ', $eavGet('sp_father_village')]],
            ],
            // P10 mother (own) / P17 mother (spouse's) — occurrence 0,1
            'PREFIXຊື່ແມ່' => [
                [['dots', 'ຊື່ແມ່', $eavGet('mother_name')], ['dots', 'ອາຍຸ', $eavGet('mother_age')], ['dots', 'ອາຊີບ', $eavGet('mother_occupation')], ['dots', 'ບ້ານ', $eavGet('mother_village')]],
                [['dots', 'ຊື່ແມ່', $eavGet('sp_mother_name')], ['dots', 'ອາຍຸ', $eavGet('sp_mother_age')], ['dots', 'ອາຊີບ', $eavGet('sp_mother_occupation')], ['dots', 'ບ້ານ', $eavGet('sp_mother_village')]],
            ],
            // P11-13 siblings (own), P18-20 spouse rels, P22-24 children, P26-28 other rels — 12 occurrences
            'PREFIXຊື່' => [
                [['dots', 'ຊື່', $eavGet('sib1_name')], ['dots', 'ອາຍຸ', $eavGet('sib1_age')], ['dots', 'ອາຊີບ', $eavGet('sib1_occupation')], ['dots', 'ບ້ານ', $eavGet('sib1_village')]],
                [['dots', 'ຊື່', $eavGet('sib2_name')], ['dots', 'ອາຍຸ', $eavGet('sib2_age')], ['dots', 'ອາຊີບ', $eavGet('sib2_occupation')], ['dots', 'ບ້ານ', $eavGet('sib2_village')]],
                [['dots', 'ຊື່', $eavGet('sib3_name')], ['dots', 'ອາຍຸ', $eavGet('sib3_age')], ['dots', 'ອາຊີບ', $eavGet('sib3_occupation')], ['dots', 'ບ້ານ', $eavGet('sib3_village')]],
                [['dots', 'ຊື່', $eavGet('spr1_name')], ['dots', 'ອາຍຸ', $eavGet('spr1_age')], ['dots', 'ອາຊີບ', $eavGet('spr1_occupation')], ['dots', 'ບ້ານ', $eavGet('spr1_village')]],
                [['dots', 'ຊື່', $eavGet('spr2_name')], ['dots', 'ອາຍຸ', $eavGet('spr2_age')], ['dots', 'ອາຊີບ', $eavGet('spr2_occupation')], ['dots', 'ບ້ານ', $eavGet('spr2_village')]],
                [['dots', 'ຊື່', $eavGet('spr3_name')], ['dots', 'ອາຍຸ', $eavGet('spr3_age')], ['dots', 'ອາຊີບ', $eavGet('spr3_occupation')], ['dots', 'ບ້ານ', $eavGet('spr3_village')]],
                [['dots', 'ຊື່', $eavGet('child1_name')], ['dots', 'ອາຍຸ', $eavGet('child1_age')], ['dots', 'ອາຊີບ', $eavGet('child1_occupation')], ['dots', 'ບ້ານ', $eavGet('child1_village')]],
                [['dots', 'ຊື່', $eavGet('child2_name')], ['dots', 'ອາຍຸ', $eavGet('child2_age')], ['dots', 'ອາຊີບ', $eavGet('child2_occupation')], ['dots', 'ບ້ານ', $eavGet('child2_village')]],
                [['dots', 'ຊື່', $eavGet('child3_name')], ['dots', 'ອາຍຸ', $eavGet('child3_age')], ['dots', 'ອາຊີບ', $eavGet('child3_occupation')], ['dots', 'ບ້ານ', $eavGet('child3_village')]],
                [['dots', 'ຊື່', $eavGet('rel1_name')], ['dots', 'ອາຍຸ', $eavGet('rel1_age')], ['dots', 'ອາຊີບ', $eavGet('rel1_occupation')], ['dots', 'ບ້ານ', $eavGet('rel1_village')]],
                [['dots', 'ຊື່', $eavGet('rel2_name')], ['dots', 'ອາຍຸ', $eavGet('rel2_age')], ['dots', 'ອາຊີບ', $eavGet('rel2_occupation')], ['dots', 'ບ້ານ', $eavGet('rel2_village')]],
                [['dots', 'ຊື່', $eavGet('rel3_name')], ['dots', 'ອາຍຸ', $eavGet('rel3_age')], ['dots', 'ອາຊີບ', $eavGet('rel3_occupation')], ['dots', 'ບ້ານ', $eavGet('rel3_village')]],
            ],
            // P14 spouse
            'ຊື່ ແລະ ນາມສະກຸນຂອງຜົວ ຫຼື ເມຍ' => [
                ['dots', 'ຊື່ ແລະ ນາມສະກຸນຂອງຜົວ ຫຼື ເມຍ', $eavGet('spouse_name')],
                ['dots', 'ວັນ,ເດືອນ,ປີເກີດ', $eavGet('spouse_dob')],
                ['dots', 'ສັນຊາດ', $eavGet('spouse_nationality')],
                ['dots', 'ອາຊີບ', $eavGet('spouse_occupation')],
                ['dots', 'ປະຈຸບັນຢູ່ໜ່ວຍ', $eavGet('spouse_unit')],
                ['dots', 'ບ້ານ', $eavGet('spouse_village')],
                ['dots', 'ເມືອງ', $eavGet('spouse_district')],
                ['dots', 'ແຂວງ', $eavGet('spouse_province')],
            ],
        ];
    } elseif ((int)$row['doc_type_id'] === 6 && (int)$row['doc_item_id'] === 28) {
    // ສັນຍາກູ້ຢືມເງິນ (loan contract, doc_type 6, item 28)
    // Party A = applicant (lender ຝ່າຍ ກໍ); Party B = borrower citizen (buyer_id).
    // Two identical "ຂ້າພະເຈົ້າ ຊື່:" lines (P5 = A, P7 = B) -> occurrence-aware.
    $eavGet = function($k) use ($eav) { return trim((string)($eav[$k] ?? '')); };
    $partyAName = trim($nameTitle . ' ' . $row['first_name'] . ' ' . $row['last_name']);
    $partyAAge  = $row['dob'] ? floor((time() - strtotime($row['dob'])) / 31536000) : '';
    $partyBName = trim(($row['buyer_title'] ?? '') . ' ' . ($row['buyer_first_name'] ?? '') . ' ' . ($row['buyer_last_name'] ?? ''));
    $partyBAge  = $row['buyer_dob'] ? floor((time() - strtotime($row['buyer_dob'])) / 31536000) : '';
    // Party A / B resolvers (borrower = buyer citizen geo + lookups)
    $pbNat = resolveName($conn, 'edoc_country', $row['buyer_nationality'] ?? '');
    $pbOcc = resolveName($conn, 'edoc_occupation', $row['buyer_occupation'] ?? '');
    $opsByAnchor = [
        'PREFIXວັນທີ' => [['dots', 'ວັນທີ', $today]],
        // P18 place + date line (date is .........../........../........... -> dotspan)
        'ທີ່,' => [['dots', 'ທີ່,', $row['taseng_name'] ?? ''], ['dotspan', 'ວັນທີ :', $today]],
        // P5 Party A (lender / applicant) — occurrence 0
        'PREFIXຂ້າພະເຈົ້າ ຊື່:' => [
            [['dots', 'ຊື່:', $partyAName],
             ['dots', 'ອາຍຸ', $partyAAge],
             ['dots', 'ອາຊີບ', $occupationName],
             ['dots', 'ເຊື້ອຊາດ', resolveName($conn, 'edoc_country', $row['origin'] ?? '')],
             ['dots', 'ສັນຊາດ', $nationalityName],
             ['dots', 'ສາສະໜາ', resolveName($conn, 'edoc_religion', $row['religion'] ?? '')],
             ['dots', 'ຊົນເຜົ່າ', resolveName($conn, 'edoc_ethnicity', $row['ethnicity'] ?? '')],
             ['dots', 'ປັດຈຸບັນຢູ່ເຮືອນເລກທີ', $row['house_no']],
             ['dots', 'ໜ່ວຍ', $row['unit_no']],
             ['dots', 'ບ້ານ:', $row['village_name']],
             ['dots', 'ເມືອງ:', $row['district_name'] ?? ''],
             ['dots', 'ແຂວງ/ນະຄອນ', $row['province_name'] ?? '']],
            // P7 Party B (borrower / buyer citizen) — occurrence 1
            [['dots', 'ຊື່:', $partyBName],
             ['dots', 'ອາຍຸ', $partyBAge],
             ['dots', 'ອາຊີບ', $pbOcc],
             ['dots', 'ເຊື້ອຊາດ', resolveName($conn, 'edoc_country', $row['buyer_origin'] ?? '')],
             ['dots', 'ສັນຊາດ', $pbNat],
             ['dots', 'ສາສະໜາ', resolveName($conn, 'edoc_religion', $row['buyer_religion'] ?? '')],
             ['dots', 'ຊົນເຜົ່າ', resolveName($conn, 'edoc_ethnicity', $row['buyer_ethnicity'] ?? '')],
             ['dots', 'ປັດຈຸບັນຢູ່ເຮືອນເລກທີ', $row['buyer_house_no'] ?? ''],
             ['dots', 'ໜ່ວຍ', $row['buyer_unit_no'] ?? ''],
             ['dots', 'ບ້ານ:', $row['buyer_village_name'] ?? ''],
             ['dots', 'ເມືອງ:', $row['buyer_district_name'] ?? ''],
             ['dots', 'ແຂວງ/ນະຄອນ', $row['buyer_province_name'] ?? ''],
             ['dots', 'ຂື້ນສຳມະໂນຄົວເລກທີ', $eavGet('party_b_family_book_no')],
             ['dots', 'ລົງວັນທີ', fmtDate($eavGet('party_b_family_book_date'))],
             ['dots', 'ບັດປະຈຳຕົວເລກທີ', $row['buyer_id_card'] ?? ''],
             ['dots', 'ເບີໂທລະສັບ', $row['buyer_phone'] ?? '']],
        ],
        // P6 Party A family book / id card / phone (applicant's own; lines after P5)
        'PREFIXຂື້ນສຳມະໂນຄົວເລກທີ' => [
            [['dots', 'ຂື້ນສຳມະໂນຄົວເລກທີ', $eavGet('party_a_family_book_no')],
             ['dots', 'ລົງວັນທີ', fmtDate($eavGet('party_a_family_book_date'))],
             ['dots', 'ບັດປະຈຳຕົວເລກທີ', $row['id_card']],
             ['dots', 'ເບີໂທລະສັບ', $row['phone']]],
        ],
        // P10 loan amount + P11 amount in words (parens on its own line)
        'ຢືມເງິນຈໍານວນ' => [
            ['dots', 'ຈໍານວນ', $eavGet('loan_amount')],
        ],
        'ເພື່ອນໍາໄປໝູນ' => [
            ['dots', '(', $eavGet('loan_amount_words')],
        ],
        // P12 loan period (start / end / months)
        'ເລີ່ມແຕ່ວັນທີ' => [
            ['dots', 'ວັນທີ:', fmtDate($eavGet('loan_start_date'))],
            ['dots', 'ຫາ', fmtDate($eavGet('loan_end_date'))],
            ['dots', '(', $eavGet('loan_months')],
        ],
        // P13 interest rate
                'ດອກເບ້ຍຈາກຝ່າຍ' => [
                                    ['dots', 'ຈຳນວນ:', $eavGet('loan_interest_pct')],
                                ],
                            ];
                    } elseif ((int)$row['doc_type_id'] === 2 && (int)$row['doc_item_id'] === 20) {
    // ໃບຄຳຮ້ອງ ຂໍຈົດທະບຽນແຕ່ງດອງ (Marriage Registration Request, doc_type 2, item 20)
    // Two-party: groom = applicant citizen; bride = buyer citizen (buyer_id).
    // Recipient P5, engagement-note ref P11, groom profile P13, bride profile P14.
    $eavGet = function($k) use ($eav) { return trim((string)($eav[$k] ?? '')); };
    $groomName = trim($row['first_name'] . ' ' . $row['last_name']);
    $groomAge  = $row['dob'] ? floor((time() - strtotime($row['dob'])) / 31536000) : '';
    $brideName = trim(($row['buyer_first_name'] ?? '') . ' ' . ($row['buyer_last_name'] ?? ''));
    $brideAge  = $row['buyer_dob'] ? floor((time() - strtotime($row['buyer_dob'])) / 31536000) : '';
    $brideNat  = resolveName($conn, 'edoc_country', $row['buyer_nationality'] ?? '');
    $brideOcc  = resolveName($conn, 'edoc_occupation', $row['buyer_occupation'] ?? '');
    $opsByAnchor = [
        // P5 recipient + taseng
        'PREFIXຮຽນ:' => [['dots', 'ທ່ານ', $eavGet('marry_recipient')], ['dots', 'ຕາແສງ', $row['taseng_name'] ?? '']],
        // P11 engagement-note ref
        'PREFIXສະບັບເລກທີ' => [['dots', 'ສະບັບເລກທີ', $eavGet('marry_sou_khor_no')], ['dots', 'ລົງວັນທີ', fmtDate($eavGet('marry_sou_khor_date'))]],
        // P13 groom profile (applicant) + bride name at line end
        'PREFIXພວກຂ້າພະເຈົ້າທ້າວ' => [
            [['dots', 'ທ້າວ', $groomName],
             ['dots', 'ວັນ,ເດືອນ,ປີເກິດ', $row['dob'] ? date('d/m/Y', strtotime($row['dob'])) : ''],
             ['dots', 'ອາຊີບ', $occupationName],
             ['dots', 'ເຊື້ອຊາດ', $originName],
             ['dots', 'ສັນຊາດ', $nationalityName],
             ['dots', 'ເຊົາເຜົ່າ', $ethnicityName],
             ['dots', 'ສາສະໜາ', $religionName],
             ['dots', 'ເຮືອນເລກທີ', $row['house_no'] ?? ''],
             ['dots', 'ໜ່ວຍ', $row['unit_no'] ?? ''],
             ['dots', 'ບ້ານ', $row['village_name']],
             ['dots', 'ເມືອງ', $row['district_name'] ?? ''],
             ['dots', 'ແຂວງ', $row['province_name'] ?? ''],
             ['dots', 'ສຳມະໂນຄົວເລກທີ', $eavGet('marry_family_book_no')],
             ['dots', 'ລົງວັນທີ', fmtDate($eavGet('marry_family_book_date'))],
             ['dots', 'ເບີໂທລະສັບ', $row['phone']],
             ['dots', 'ແລະ ນາງ', $brideName]],
        ],
        // P14 bride profile continuation (buyer)
        'PREFIXວັນ,ເດືອນ,ປີເກິດ' => [
            [['dots', 'ວັນ,ເດືອນ,ປີເກິດ', $row['buyer_dob'] ? date('d/m/Y', strtotime($row['buyer_dob'])) : ''],
             ['dots', 'ອາຊີບ', $brideOcc],
             ['dots', 'ເຊື້ອຊາດ', resolveName($conn, 'edoc_ethnicity', $row['buyer_origin'] ?? '')],
             ['dots', 'ສັນຊາດ', $brideNat],
             ['dots', 'ເຊົາເຜົ່າ', resolveName($conn, 'edoc_ethnicity', $row['buyer_ethnicity'] ?? '')],
             ['dots', 'ສາສະໜາ', resolveName($conn, 'edoc_religion', $row['buyer_religion'] ?? '')],
             ['dots', 'ເຮືອນເລກທີ', $row['buyer_house_no'] ?? ''],
             ['dots', 'ໜ່ວຍ', $row['buyer_unit_no'] ?? ''],
             ['dots', 'ບ້ານ', $row['buyer_village_name'] ?? ''],
             ['dots', 'ເມືອງ', $row['buyer_district_name'] ?? ''],
             ['dots', 'ແຂວງ', $row['buyer_province_name'] ?? ''],
             ['dots', 'ສຳມະໂນຄົວເລກທີ', $eavGet('marry_bride_family_book_no')],
             ['dots', 'ລົງວັນທີ', fmtDate($eavGet('marry_bride_family_book_date'))],
             ['dots', 'ເບີໂທລະສັບ', $row['buyer_phone'] ?? '']],
        ],
        // P22 signature place + date
        'PREFIXທີ່' => [['dots', 'ທີ່', $row['province_name'] ?? ''], ['dots', 'ວັນທີ', $today]],
    ];
                    } elseif ((int)$row['doc_type_id'] === 6 && (int)$row['doc_item_id'] === 53) {
                    // ສັນຍາຊື້-ຂາຍ ກຳມະສິດທີ່ດິນ (land sale contract, doc_type 6, item 31)
                    // Seller = applicant citizen; Buyer = second citizen (buyer_id).
                    // Seller/buyer continuation lines are textually identical (ເຊື້ອຊາດ...,
                    // ຂື້ນສຳມະໂນຄົວ..., ລົງວັນທີ...) -> occurrence-aware PREFIX.
                    $eavGet = function($k) use ($eav) { return trim((string)($eav[$k] ?? '')); };
                    $sellerName = trim($nameTitle . ' ' . $row['first_name'] . ' ' . $row['last_name']);
                    $sellerAge  = $row['dob'] ? floor((time() - strtotime($row['dob'])) / 31536000) : '';
                    $buyerName = trim(($row['buyer_title'] ?? '') . ' ' . ($row['buyer_first_name'] ?? '') . ' ' . ($row['buyer_last_name'] ?? ''));
                    $buyerAge  = $row['buyer_dob'] ? floor((time() - strtotime($row['buyer_dob'])) / 31536000) : '';
                    $bnNat = resolveName($conn, 'edoc_country', $row['buyer_nationality'] ?? '');
                    $bnOcc = resolveName($conn, 'edoc_occupation', $row['buyer_occupation'] ?? '');
                    $opsByAnchor = [
                        // P6 agreement date (……......../…….....……...)
                        'ຄັ້ງວັນທີ' => [['dotspan', 'ຄັ້ງວັນທີ', $today]],
                        // P8 seller name line (applicant) / P13 buyer name line
                        'PREFIXຂ້າພະເຈົ້າ ຜູ້ຂາຍ:' => [
                            [['dots', 'ຜູ້ຂາຍ:', $sellerName], ['dots', 'ອາຍຸ', $sellerAge], ['dots', 'ອາຊີບ', $occupationName]],
                        ],
                        'PREFIXຂ້າພະເຈົ້າ ຜູ້ຊື້:' => [
                            [['dots', 'ຜູ້ຊື້:', $buyerName], ['dots', 'ອາຍຸ', $buyerAge], ['dots', 'ອາຊີບ', $bnOcc]],
                        ],
                        // P9 seller profile continuation / P14 buyer profile continuation (identical text)
                        'PREFIXເຊື້ອຊາດ' => [
                            [['dots', 'ເຊື້ອຊາດ', resolveName($conn, 'edoc_country', $row['origin'] ?? '')],
                             ['dots', 'ສັນຊາດ', $nationalityName],
                             ['dots', 'ສາສະໜາ', resolveName($conn, 'edoc_religion', $row['religion'] ?? '')],
                             ['dots', 'ຊົນເຜົ່າ', resolveName($conn, 'edoc_ethnicity', $row['ethnicity'] ?? '')],
                             ['dots', 'ປັດຈຸບັນຢູ່ເຮືອນເລກທີ', $row['house_no']],
                             ['dots', 'ໜ່ວຍ', $row['unit_no']],
                             ['dots', 'ບ້ານ:', $row['village_name']],
                             ['dots', 'ເມືອງ:', $row['district_name'] ?? ''],
                             ['dots', 'ແຂວງ,/ນະຄອນ', $row['province_name'] ?? '']],
                            [['dots', 'ເຊື້ອຊາດ', resolveName($conn, 'edoc_country', $row['buyer_origin'] ?? '')],
                             ['dots', 'ສັນຊາດ', $bnNat],
                             ['dots', 'ສາສະໜາ', resolveName($conn, 'edoc_religion', $row['buyer_religion'] ?? '')],
                             ['dots', 'ຊົນເຜົ່າ', resolveName($conn, 'edoc_ethnicity', $row['buyer_ethnicity'] ?? '')],
                             ['dots', 'ປັດຈຸບັນຢູ່ເຮືອນເລກທີ', $row['buyer_house_no'] ?? ''],
                             ['dots', 'ໜ່ວຍ', $row['buyer_unit_no'] ?? ''],
                             ['dots', 'ບ້ານ:', $row['buyer_village_name'] ?? ''],
                             ['dots', 'ເມືອງ:', $row['buyer_district_name'] ?? ''],
                             ['dots', 'ແຂວງ,/ນະຄອນ', $row['buyer_province_name'] ?? '']],
                        ],
                        // P10 seller family book/id / P15 buyer family book/id (identical text)
                        'PREFIXຂື້ນສຳມະໂນຄົວເລກທີ' => [
                            [['dots', 'ຂື້ນສຳມະໂນຄົວເລກທີ', $eavGet('seller_family_book_no')],
                             ['dots', 'ລົງວັນທີ', fmtDate($eavGet('seller_family_book_date'))],
                             ['dots', 'ບັດປະຈຳຕົວເລກທີ', $row['id_card']]],
                            [['dots', 'ຂື້ນສຳມະໂນຄົວເລກທີ', $eavGet('buyer_family_book_no')],
                             ['dots', 'ລົງວັນທີ', fmtDate($eavGet('buyer_family_book_date'))],
                             ['dots', 'ບັດປະຈຳຕົວເລກທີ', $row['buyer_id_card'] ?? '']],
                        ],
                        // P11 seller id date/phone / P16 buyer id date/phone (identical text)
                        'PREFIXລົງວັນທີ' => [
                            [['dots', 'ລົງວັນທີ', fmtDate($row['id_card_issue_date'])], ['dots', 'ເບີໂທລະສັບ', $row['phone']]],
                            [['dots', 'ລົງວັນທີ', fmtDate($row['buyer_id_card_issue_date'] ?? '')], ['dots', 'ເບີໂທລະສັບ', $row['buyer_phone'] ?? '']],
                        ],
                        // P18 ມາດຕາ 1 land details
                        'PREFIXມາດຕາ 1' => [
                            ['dotspan', 'ຕັ້ງຢູ່ບ້ານ', $eavGet('land_village')],
                            ['dotspan', 'ເມືອງ', $eavGet('land_district')],
                            ['dotspan', 'ແຂວງ', $eavGet('land_province')],
                            ['dotspan', 'ເນື້ອທີ່ດິນ', $eavGet('land_area_m2')],
                            ['dotspan', 'ໃບຕາເລກທີ:', $eavGet('land_deed_no')],
                            ['dotspan', 'ແຜນຕາດິນເລກທີ:', $eavGet('land_map_no')],
                            ['dotspan', 'ອອກຊື່:', $eavGet('land_owner_name')],
                            ['dotspan', 'ລົງວັນທີ', fmtDate($eavGet('land_owner_date'))],
                        ],
                        // P20 ມາດຕາ 2 price line (total + paid)
                        'PREFIXລວມລາຄາ' => [
                            ['dotspan', 'ລວມລາຄາ:', $eavGet('total_price')],
                            ['dots', 'ເປັນຕົວໜັງສື:', $eavGet('total_price_words')],
                            ['dotspan', 'ແລ້ວຈຳນວນ', $eavGet('paid_amount')],
                        ],
                        // P21 ມາດຕາ 2 payment line (paid words / remaining / deadline / after-sale / days)
                        'ສ່ວນຈຳນວນທີ່ຍັງເຫຼືອ' => [
                            ['dots', 'ເປັນຕົວໜັງສື:', $eavGet('paid_amount_words')],
                            ['dotspan', 'ສ່ວນຈຳນວນທີ່ຍັງເຫຼືອ', $eavGet('remaining_amount')],
                            ['dots2', 'ເປັນຕົວໜັງສື:', $eavGet('remaining_words')],
                            ['dotspan', 'ພາຍຫຼັງ', $eavGet('deadline_after')],
                            ['dotspan', 'ອອກເປັນຊື່', $eavGet('after_sale_name')],
                            ['dotspan', 'ກາຍ', $eavGet('deadline_days')],
                        ],
                        // P24 ມາດຕາ 3 penalty
                        'ຄ່າປັບໃໝ' => [
                            ['dotspan', 'ເປັນຈຳນວນ', $eavGet('penalty_amount')],
                        ],
                        // P39 signature names (seller / buyer)
                            'ຊື່ແຈ້ງ' => [
                                ['dots', 'ຊື່ແຈ້ງ:', $sellerName],
                                ['dots2', 'ຊື່ແຈ້ງ:', $buyerName],
                            ],
                        ];
                        } elseif ((int)$row['doc_type_id'] === 6 && (int)$row['doc_item_id'] === 30) {
    // ໃບສັນຍາເຊົ່າທີ່ດິນ ແລະ ເຮືອນ (Land & House Lease Contract, doc_type 6, item 30)
    // Owner ເຈົ້າຂອງຊັບ = applicant citizen; Renter ຜູ້ເຂົ້າເຊົ່າ = buyer citizen.
    // P9/P12 (ຊື່ຜົວ ຫຼື ເມຍ) and P10/P13 (ທີ່ຢູ່ປະຈຸບັນບ້ານ) are identical
    // two-party lines -> occurrence-aware. Direction table (P16-19) stays blank.
    $eavGet = function($k) use ($eav) { return trim((string)($eav[$k] ?? '')); };
    $ownerName = trim($row['first_name'] . ' ' . $row['last_name']);
    $ownerAge  = $row['dob'] ? floor((time() - strtotime($row['dob'])) / 31536000) : '';
    $renterName = trim(($row['buyer_first_name'] ?? '') . ' ' . ($row['buyer_last_name'] ?? ''));
    $renterAge  = $row['buyer_dob'] ? floor((time() - strtotime($row['buyer_dob'])) / 31536000) : '';
    $renterNat = resolveName($conn, 'edoc_country', $row['buyer_nationality'] ?? '');
    $renterOcc = resolveName($conn, 'edoc_occupation', $row['buyer_occupation'] ?? '');
    $opsByAnchor = [
        // P3 doc ref + P4 date
        'ຄປຕ' => [['dots', 'ເລກທີ', $row['doc_no']]],
        'PREFIXລົງວັນທີ' => [['dots', 'ລົງວັນທີ', $today]],
        // P8 owner name line / P9 owner spouse / P10 owner address
        'PREFIXລະຫວ່າງ' => [
            [['dots', 'ທ້າວ ຫຼື ນາງ', $ownerName], ['dots', 'ອາຍຸ', $ownerAge], ['dots', 'ສັນຊາດ', $nationalityName], ['dots', 'ອາຊີບ', $occupationName]],
        ],
        // P9 owner spouse / P12 renter spouse (identical ຊື່ຜົວ ຫຼື ເມຍ lines)
        'PREFIXຊື່ຜົວ ຫຼື ເມຍ' => [
            [['dots', 'ຊື່ຜົວ ຫຼື ເມຍ', $eavGet('lease_owner_spouse')]],
            [['dots', 'ຊື່ຜົວ ຫຼື ເມຍ', $eavGet('lease_renter_spouse')]],
        ],
        // P10 owner address / P13 renter address (identical ທີ່ຢູ່ປະຈຸບັນບ້ານ lines)
        'PREFIXທີ່ຢູ່ປະຈຸບັນບ້ານ' => [
            [['dots', 'ບ້ານ', $row['village_name']], ['dots', 'ເມືອງ', $row['district_name'] ?? ''], ['dots', 'ແຂວງ', $row['province_name'] ?? '']],
            [['dots', 'ບ້ານ', $row['buyer_village_name'] ?? ''], ['dots', 'ເມືອງ', $row['buyer_district_name'] ?? ''], ['dots', 'ແຂວງ', $row['buyer_province_name'] ?? '']],
        ],
        // P11 renter name line (ແລະທ້າວ ຫຼື ນາງ)
        'PREFIXແລະທ້າວ ຫຼື ນາງ' => [
            [['dots', 'ທ້າວ ຫຼື ນາງ', $renterName], ['dots', 'ອາຍຸ', $renterAge], ['dots', 'ສັນຊາດ', $renterNat], ['dots', 'ອາຊີບ', $renterOcc]],
        ],
        // P14 property description
        'PREFIXເຈົ້າຂອງຊັບໄດ້ຕົກລົງ' => [
            [['dots', 'ເຈົ້າຂອງຊັບໄດ້ຕົກລົງໃຫ້ເຊົ່າ', $eavGet('lease_property_desc')]],
        ],
        // P15 land location
        'PREFIXຊຶ່ງຕັ້ງຢູ່ບ້ານ' => [
            [['dots', 'ຊຶ່ງຕັ້ງຢູ່ບ້ານ', $eavGet('land_village')], ['dots', 'ເມືອງ', $eavGet('land_district')], ['dots', 'ແຂວງ', $eavGet('land_province')]],
        ],
        // P20 area / term / start-end dates / price per year
        'PREFIXລວມເນື້ອທີ່' => [
            [['dots', 'ລວມເນື້ອທີ່:', $eavGet('lease_area_m2')],
             ['dots', 'ກໍານົດເຊົ່າ', $eavGet('lease_term')],
             ['dotspan', 'ເລີ່ມແຕ່ວັນທີ', fmtDate($eavGet('lease_start_date'))],
             ['dotspan', 'ຈົນເຖິງວັນທີ', fmtDate($eavGet('lease_end_date'))],
             ['dots', 'ໃນລາຄາ', $eavGet('lease_price_year')]],
        ],
        // P22 deposit / P23 installment
        'PREFIX1. ຈ່າຍມັດຈໍາລ່ວງໜ້າ' => [['dots', 'ເງິນຈໍານວນ:', $eavGet('lease_deposit')]],
        'PREFIX2. ຈ່າຍງວດປົກກະຕິ' => [['dots', 'ເງິນຈໍານວນ:', $eavGet('lease_installment')]],
        // P32 signature place + date
        'PREFIXທີ່' => [['dots', 'ທີ່', $row['province_name'] ?? ''], ['dotspan', 'ວັນທີ', $today]],
    ];
                    } elseif ((int)$row['doc_type_id'] === 1 && (int)$row['doc_item_id'] === 57) {
    // ໃບຢັ້ງຢືນລູກທາງ (ລູກຊູ້) — Foster/Adopted Child Certificate (doc_type 1, item 57, code 31)
    // Single-party certificate: applicant profile from citizen record; partner +
    // child + relationship details from EAV.
    $eavGet = function($k) use ($eav) { return trim((string)($eav[$k] ?? '')); };
    $personName = trim($row['first_name'] . ' ' . $row['last_name']);
    $personAge  = $row['dob'] ? floor((time() - strtotime($row['dob'])) / 31536000) : '';
    $opsByAnchor = [
        // P6 doc ref
        'ຄປຕ' => [['dots', 'ເລກທີ', $row['doc_no']]],
        // P7 date
        'PREFIXວັນທີ' => [['dots', 'ວັນທີ', $today]],
        // P11 applicant name + age
        'PREFIXໄດ້ເຮັດໃບຢັ້ງຢືນລູກຊູ້ໃຫ້' => [
            [['dots', 'ທ້າວ/ນາງ:', $personName],
             ['dots', 'ອາຍຸ', $personAge]],
        ],
        // P12 applicant occupation/address + partner profile
        'PREFIXອາຊີບ:' => [
            [['dots', 'ອາຊີບ:', $occupationName],
             ['dots', 'ບ້ານ:', $row['village_name']],
             ['dots', 'ເມືອງ:', $row['district_name'] ?? ''],
             ['dots', 'ແຂວງ:', $row['province_name'] ?? ''],
             ['dots', 'ກັບ ທ້າວ/ນາງ', $eavGet('foster_partner_name')],
             ['dots', 'ອາຍຸ', $eavGet('foster_partner_age')],
             ['dots', 'ອາຊີບ', $eavGet('foster_partner_occupation')],
             ['dots', 'ໜ່ວຍ', $eavGet('foster_partner_unit')],
             ['dots', 'ບ້ານ,', $eavGet('foster_partner_village')],
             ['dots', 'ເມືອງ', $eavGet('foster_partner_district')],
             ['dots', 'ແຂວງ:', $eavGet('foster_partner_province')],
             ['dots', 'ຂື້ນສຳມະໂນຄົວເລກທີ', $eavGet('foster_partner_family_no')],
             ['dots', 'ລົງວັນທີ', fmtDate($eavGet('foster_partner_family_date'))]],
        ],
        // P13 relationship years + children count
        'PREFIXທັງສອງແມ່ນ' => [
            [['dots', 'ຢູ່ກິນນໍາກັນມາແຕ່ປີ', $eavGet('foster_live_since_year')],
             ['dots', 'ລູກຮ່ວມກັນ', $eavGet('foster_children_count')]],
        ],
        // P14 child name + separated year
        'PREFIXຄື:' => [
            [['dots', 'ຄື: ທ້າວ/ນາງ', $eavGet('foster_child_name')],
             ['dots', 'ຈົນມາຮອດ ປີ', $eavGet('foster_separated_year')]],
        ],
        // P16 purpose
        'PREFIXເອກະສານສະບັບນີ້' => [
            [['dots', 'ໃຫ້', $eavGet('foster_purpose')]],
        ],
        // P17 signature place + date
        'PREFIXທີ່ບ້ານ' => [['dots', 'ທີ່ບ້ານ', $row['village_name']], ['dots', 'ວັນທີ', $today]],
    ];
                    } elseif ((int)$row['doc_type_id'] === 4 && (int)$row['doc_item_id'] === 4) {
    // ໃບແຈ້ງເສຍຫາຍ (Damage/Loss Notification, doc_type 4, item 4)
    // Single-party notification: applicant profile from citizen record;
    // report date/time + damage description + damage date from EAV.
    $eavGet = function($k) use ($eav) { return trim((string)($eav[$k] ?? '')); };
    $personName = trim($row['first_name'] . ' ' . $row['last_name']);
    $reportDate = $eavGet('notify_report_date');
    $reportY = $reportDate ? date('Y', strtotime($reportDate)) : '';
    $reportM = $reportDate ? date('m', strtotime($reportDate)) : '';
    $reportD = $reportDate ? date('d', strtotime($reportDate)) : '';
    $opsByAnchor = [
        // P6 doc ref + P7 date
        'ຄປຕ' => [['dots', 'ເລກທີ:', $row['doc_no']]],
        'PREFIXວັນທີ' => [['dots', 'ວັນທີ', $today]],
        // P12 report date/time
        'PREFIXຂໍຢັ້ງຢືນວ່າ' => [
            [['dots', 'ວັນທີ', $reportD],
             ['dots', 'ເດືອນ', $reportM],
             ['dots', 'ປີ', $reportY],
             ['dots', 'ເວລາ', $eavGet('notify_report_time')]],
        ],
        // P13 applicant name + DOB
        'PREFIXທ້າວ/ນາງ:' => [
            [['dots', 'ທ້າວ/ນາງ:', $personName],
             ['dots', 'ວັນ,ເດືອນ,ປີເກີດ', $row['dob'] ? date('d/m/Y', strtotime($row['dob'])) : '']],
        ],
        // P14 profile continuation
        'PREFIXສັນຊາດ' => [
            [['dots', 'ສັນຊາດ', $nationalityName],
             ['dots', 'ອາຊີບ:', $occupationName],
             ['dots', 'ເຮືອນເລກທີ', $row['house_no'] ?? ''],
             ['dots', 'ໜ່ວຍ', $row['unit_no'] ?? ''],
             ['dots', 'ບ້ານ', $row['village_name']]],
        ],
        // P15 family book (fixed ເມືອງໄຊທານີ header already in template)
        'PREFIXເມືອງໄຊທານີ' => [
            [['dots', 'ຂື້ນສໍາມະໂນຄົວເລກທີ:', $row['family_book_no'] ?? ''],
             ['dots', 'ລົງວັນທີ', fmtDate($row['family_book_date'] ?? '')]],
        ],
        // P16 damage description
        'PREFIXໄດ້ມາແຈ້ງຄວາມວ່າຕົນໄດ້ເຮັດ' => [
            [['dots', 'ໄດ້ມາແຈ້ງຄວາມວ່າຕົນໄດ້ເຮັດ', $eavGet('notify_damage_desc')]],
        ],
        // P22 damage date
        'PREFIXເສຍຫາຍໃນວັນທີ' => [
            [['dots', 'ເສຍຫາຍໃນວັນທີ', fmtDate($eavGet('notify_damage_date'))]],
        ],
        // P24 signature place + date
        'PREFIXທີ່' => [['dots', 'ທີ່', $row['province_name'] ?? ''], ['dots', 'ວັນທີ:', $today]],
    ];
                    } elseif ((int)$row['doc_type_id'] === 5 && (int)$row['doc_item_id'] === 58) {
    // ບົດບັນທຶກການຢ່າຮ້າງ (Divorce Record, doc_type 5, item 58, code 26)
    // Two-party memo: husband = applicant citizen (ໂຈດ); wife = buyer citizen (ຈຳເລີຍ).
    $eavGet = function($k) use ($eav) { return trim((string)($eav[$k] ?? '')); };
    $husbandName = trim($row['first_name'] . ' ' . $row['last_name']);
    $wifeName = trim(($row['buyer_first_name'] ?? '') . ' ' . ($row['buyer_last_name'] ?? ''));
    $opsByAnchor = [
        // P3 district header (trailing space to distinguish from P23/P26 dot-lines)
        'PREFIXເມືອງ ' => [['dots', 'ເມືອງ', $row['district_name'] ?? '']],
        // P4 taseng
        'PREFIXຕາແສງ' => [['dots', 'ຕາແສງ', $row['taseng_name'] ?? '']],
        // P5 doc ref + P6 date
        'ເລກທີ' => [['dots', 'ເລກທີ', $row['doc_no']]],
        'PREFIXລົງວັນທີ' => [['dots', 'ລົງວັນທີ', $today]],
        // P10 education memo ref
        'PREFIXອີງຕາມ ບົດບັນທຶກການສຶກສາອົບຮົມຄູ່ຜົວ-ເມຍ' => [
            [['dots', 'ສະບັບເລກທີ', $eavGet('divorce_edu_no')],
             ['dots', 'ລົງວັນທີ', fmtDate($eavGet('divorce_edu_date'))]],
        ],
        // P12 meeting place/time/date
        'PREFIXທີ່ຫ້ອງການບ້ານ' => [
            [['dots', 'ທີ່ຫ້ອງການບ້ານ', $eavGet('divorce_meet_village')],
             ['dots', 'ເວລາ', $eavGet('divorce_meet_time')],
             ['dots', 'ຂອງວັນທີ', fmtDate($eavGet('divorce_meet_date'))]],
        ],
        // P21 husband name + DOB + occupation (applicant).
        // Note: child rows start "ທ້າວ ຫຼື..." while husband line is "ທ້າວ........",
        // so anchor "ທ້າວ." (with dot) uniquely matches the husband line.
        'PREFIXທ້າວ.' => [
            [['dots', 'ທ້າວ', $husbandName],
             ['dots', 'ວັນ,ເດືອນ,ປີເກີດ', $row['dob'] ? date('d/m/Y', strtotime($row['dob'])) : ''],
             ['dots', 'ອາຊີບ', $occupationName]],
        ],
        // P22 husband profile continuation + P25 wife profile continuation
        // (identical ເຊື້ອຊາດ... prefixes -> occurrence-aware)
        'PREFIXເຊື້ອຊາດ' => [
            [['dots', 'ເຊື້ອຊາດ', $originName],
             ['dots', 'ສັນຊາດ', $nationalityName],
             ['dots', 'ເຊົາເຜົ່າ', $ethnicityName],
             ['dots', 'ສາສະໜາ', $religionName],
             ['dots', 'ໜ່ວຍ', $row['unit_no'] ?? ''],
             ['dots', 'ບ້ານ', $row['village_name']]],
            [['dots', 'ເຊື້ອຊາດ', resolveName($conn, 'edoc_ethnicity', $row['buyer_origin'] ?? '')],
             ['dots', 'ສັນຊາດ', resolveName($conn, 'edoc_country', $row['buyer_nationality'] ?? '')],
             ['dots', 'ເຊົາເຜົ່າ', resolveName($conn, 'edoc_ethnicity', $row['buyer_ethnicity'] ?? '')],
             ['dots', 'ສາສະໜາ', resolveName($conn, 'edoc_religion', $row['buyer_religion'] ?? '')],
             ['dots', 'ໜ່ວຍ', $row['buyer_unit_no'] ?? ''],
             ['dots', 'ບ້ານ', $row['buyer_village_name'] ?? '']],
        ],
        // P23 husband + P26 wife district/province/family/phone (identical ເມືອງ... prefixes)
        'PREFIXເມືອງ.' => [
            [['dots', 'ເມືອງ', $row['district_name'] ?? ''],
             ['dots', 'ແຂວງ', $row['province_name'] ?? ''],
             ['dots', 'ຂື້ນສໍາມະໂນຄົວເລກທີ', $eavGet('divorce_family_no')],
             ['dots', 'ລົງວັນທີ', fmtDate($eavGet('divorce_family_date'))],
             ['dots', 'ເບີໂທລະສັບ', $row['phone']]],
            [['dots', 'ເມືອງ', $row['buyer_district_name'] ?? ''],
             ['dots', 'ແຂວງ', $row['buyer_province_name'] ?? ''],
             ['dots', 'ຂື້ນສໍາມະໂນຄົວເລກທີ', $eavGet('divorce_bfamily_no')],
             ['dots', 'ລົງວັນທີ', fmtDate($eavGet('divorce_bfamily_date'))],
             ['dots', 'ເບີໂທລະສັບ', $row['buyer_phone'] ?? '']],
        ],
        // P24 wife name + DOB + occupation (buyer) + P46 closing wife name
                'PREFIXນາງ' => [
                    [['dots', 'ນາງ', $wifeName],
                     ['dots', 'ວັນ,ເດືອນ,ປີເກີດ', $row['buyer_dob'] ? date('d/m/Y', strtotime($row['buyer_dob'])) : ''],
                     ['dots', 'ອາຊີບ', resolveName($conn, 'edoc_occupation', $row['buyer_occupation'] ?? '')]],
                    [['dots', 'ນາງ', $wifeName]],
                ],
        // P32 mediation village + think months
        'PREFIXຄະນະໜ່ວຍໄກ່ເກັ່ຍຂໍ້ຂັດແຍ່ງຂັ້ນບ້ານ' => [
            [['dots', 'ຄະນະໜ່ວຍໄກ່ເກັ່ຍຂໍ້ຂັດແຍ່ງຂັ້ນບ້ານ', $eavGet('divorce_med_village')],
             ['dots', 'ເປັນເວລາ', $eavGet('divorce_think_months')]],
        ],
        // P33 children count
        'PREFIXຄູ່ຜົວ-ເມຍມີລູກຮ່ວມກັນ' => [
            [['dots', 'ຄູ່ຜົວ-ເມຍມີລູກຮ່ວມກັນ', $eavGet('divorce_children')]],
        ],
        // P34-38 child rows (identical ທ້າວ ຫຼື ນາງ... prefixes -> occurrence-aware)
        'PREFIXທ້າວ ຫຼື ນາງ' => [
            [['dots', 'ທ້າວ ຫຼື ນາງ', $eavGet('divorce_child1')]],
            [['dots', 'ທ້າວ ຫຼື ນາງ', $eavGet('divorce_child2')]],
            [['dots', 'ທ້າວ ຫຼື ນາງ', $eavGet('divorce_child3')]],
        ],
        // P39 custody + support
        'PREFIXຄູ່ຜົວ-ເມຍ ຕົກລົງ' => [
            [['dots', 'ຄູ່ຜົວ-ເມຍ ຕົກລົງ', $eavGet('divorce_custody')],
             ['dots', 'ຮັບຜິດຊອບລ້ຽງດູລູກ', $eavGet('divorce_support')]],
        ],
        // P40-43 assets/debts
        'PREFIXຊັບເດີມ' => [['dots', 'ຊັບເດີມ', $eavGet('divorce_original')]],
        'PREFIXສິນສົມສ້າງ' => [['dots', 'ສິນສົມສ້າງ', $eavGet('divorce_joint')]],
        'PREFIXໜີ້ຕ້ອງຮັບ' => [['dots', 'ໜີ້ຕ້ອງຮັບ', $eavGet('divorce_debt_pay')]],
        'PREFIXໜີ້ຕ້ອງສົ່ງ' => [['dots', 'ໜີ້ຕ້ອງສົ່ງ', $eavGet('divorce_debt_send')]],
        // P45 closing husband name
        'PREFIXດັ່ງນັ້ນຄະນະໜ່ວຍໄກ່ເກັ່ຍ' => [
            [['dots', 'ທ້າວ', $husbandName]],
        ],
    ];
                    } elseif ((int)$row['doc_type_id'] === 1 && (int)$row['doc_item_id'] === 56) {
    // ໃບຢັ້ງຢືນຄວາມເປັນໂສດ (Single Status Certificate, doc_type 1, item 56, code 30)
    // Single-party certificate: applicant profile from citizen record; father +
    // mother names + parents address from EAV.
    $eavGet = function($k) use ($eav) { return trim((string)($eav[$k] ?? '')); };
    $personName = trim($row['first_name'] . ' ' . $row['last_name']);
    $personAge  = $row['dob'] ? floor((time() - strtotime($row['dob'])) / 31536000) : '';
    $opsByAnchor = [
        // P5 doc ref
        'ຄປຕ' => [['dots', 'ເລກທີ', $row['doc_no']]],
        // P6 date
        'PREFIXວັນທີ' => [['dots', 'ວັນທີ', $today]],
        // P11 main body — applicant profile + family book + issued-by + parents
        'PREFIXຂໍຢັ້ງຢືນວ່າທ້າວ/ນາງ' => [
            [['dots', 'ທ້າວ/ນາງ', $personName],
             ['dots', 'ອາຍຸ', $personAge],
             ['dots', 'ເຊື້ອຊາດ', $originName],
             ['dots', 'ສັນຊາດ', $nationalityName],
             ['dots', 'ຊົນເຜົ່າ', $ethnicityName],
             ['dots', 'ສາສະໜາ', $religionName],
             ['dots', 'ອາຊີບ', $occupationName],
             ['dots', 'ບ້ານ', $row['village_name']],
             ['dots', 'ເມືອງ', $row['district_name'] ?? ''],
             ['dots', 'ແຂວງ', $row['province_name'] ?? ''],
             ['dots', 'ສຳມະໂນຄົວເລກທີ', $eavGet('single_family_book_no')],
             ['dots', 'ລົງວັນທີ', fmtDate($eavGet('single_family_book_date'))],
             ['dots', 'ອອກໃຫ້ໂດຍ', $eavGet('single_issued_by')],
             ['dots', 'ເປັນລູກຂອງທ້າວ', $eavGet('single_father_name')],
             ['dots', 'ແລະ ນາງ', $eavGet('single_mother_name')],
             ['dots', 'ຢູ່ບ້ານ', $eavGet('single_parent_village')],
             ['dots', 'ເມືອງ', $eavGet('single_parent_district')],
             ['dots', 'ແຂວງ', $eavGet('single_parent_province')]],
        ],
    ];
                    } elseif ((int)$row['doc_type_id'] === 1 && (int)$row['doc_item_id'] === 55) {
    // ໃບຢັ້ງຢືນການເສຍຫາຍ (Damage/Loss Certificate, doc_type 1, item 55, code 29)
    // Single-party certificate: applicant = person reporting damage. Profile from
    // citizen record; damage description from EAV.
    $eavGet = function($k) use ($eav) { return trim((string)($eav[$k] ?? '')); };
    $opsByAnchor = [
        // P4 district
        'PREFIXເມືອງ:' => [['dots', 'ເມືອງ:', $row['district_name'] ?? '']],
        // P5 taseng + doc ref (same paragraph, one anchor)
        'PREFIXຕາແສງ:' => [['dots', 'ຕາແສງ:', $row['taseng_name'] ?? ''], ['dots', 'ເລກທີ:', $row['doc_no']]],
        // P6 unit + date (same paragraph, one anchor)
        'PREFIXໜ່ວຍ:' => [['dots', 'ໜ່ວຍ:', $row['unit_no'] ?? ''], ['dots', 'ວັນທີ', $today]],
        // P11 applicant name + DOB
        'PREFIXໄດ້ມີທ້າວຫຼືນາງ:' => [
            [['dots', 'ທ້າວຫຼືນາງ:', trim($row['first_name'] . ' ' . $row['last_name'])],
             ['dots', 'ວັນ,ເດືອນ,ປີເກີດ', $row['dob'] ? date('d/m/Y', strtotime($row['dob'])) : '']],
        ],
        // P12 profile continuation
        'PREFIXເຊື້ອຊາດ' => [
            [['dots', 'ເຊື້ອຊາດ', $originName],
             ['dots', 'ສັນຊາດ', $nationalityName],
             ['dots', 'ຊົນເຜົ່າ', $ethnicityName],
             ['dots', 'ສາສະໜາ', $religionName],
             ['dots', 'ອາຊີບ:', $occupationName],
             ['dots', 'ບ້ານ', $row['village_name']],
             ['dots', 'ເມືອງ', $row['district_name'] ?? ''],
             ['dots', 'ແຂວງ:', $row['province_name'] ?? '']],
        ],
        // P13 damage description
        'PREFIXໄດ້ມາແຈ້ງຄວາມວ່າຕົນໄດ້ເຮັດ' => [
            [['dots', 'ໄດ້ມາແຈ້ງຄວາມວ່າຕົນໄດ້ເຮັດ', $eavGet('damage_desc')]],
        ],
        // P17 signature place + date
        'PREFIXທີ່' => [['dots', 'ທີ່', $row['province_name'] ?? ''], ['dots', 'ວັນທີ:', $today]],
    ];
                    } elseif ((int)$row['doc_type_id'] === 1 && (int)$row['doc_item_id'] === 54) {
    // ໃບຍົກຍ້າຍ (Relocation Certificate, doc_type 1, item 54, code 28)
    // Single-party certificate: applicant = person being transferred. Name/DOB from
    // citizen record; recipient, subject, legal basis, org, position, grades, from/to = EAV.
    $eavGet = function($k) use ($eav) { return trim((string)($eav[$k] ?? '')); };
    $personName = trim($nameTitle . ' ' . $row['first_name'] . ' ' . $row['last_name']);
    $opsByAnchor = [
        // P5 doc ref + P6 date
        'ຄປຕ' => [['dots', 'ເລກທີ', $row['doc_no']]],
        'PREFIXທີ່' => [['dots', 'ທີ່', $row['province_name'] ?? ''], ['dots', 'ວັນທີ', $today]],
        // P10 recipient / P11 subject
        'PREFIXຮຽນ:' => [['dots', 'ຮຽນ:', $eavGet('relocate_recipient')]],
        'PREFIXເລື່ອງ:' => [['dots', 'ເລື່ອງ:', $eavGet('relocate_subject')]],
        // P13 basis1 / P15 basis2 (identical ອີງຕາມ: prefixes)
        'PREFIXອີງຕາມ:' => [
            [['dots', 'ອີງຕາມ:', $eavGet('relocate_basis1')]],
            [['dots', 'ອີງຕາມ:', $eavGet('relocate_basis2')]],
        ],
        // P17 org name (appears again in P20)
        'PREFIXຊື່ອົງການຈັດຕັ້ງ' => [['dots', 'ຊື່ອົງການຈັດຕັ້ງ', $eavGet('relocate_org_name')]],
        // P18 person details (name, DOB, position, years, grade, salary, education, from)
        'PREFIXເຫັນດີຍົກຍ້າຍ' => [
            [['dots', 'ເຫັນດີຍົກຍ້າຍທ່ານ', $personName],
             ['dots', 'ວັນ, ເດືອນ, ປີເກີດ', $row['dob'] ? date('d/m/Y', strtotime($row['dob'])) : ''],
             ['dots', 'ຕຳແໜ່ງ', $eavGet('relocate_position')],
             ['dots', 'ປີການ', $eavGet('relocate_years')],
             ['dots', 'ຊັ້ນ', $eavGet('relocate_grade')],
             ['dots', 'ຂັ້ນເງິນເດືອນ', $eavGet('relocate_salary')],
             ['dots', 'ວຸດທິການສຶກສາ', $eavGet('relocate_education')],
             ['dots', 'ຈາກ', $eavGet('relocate_from')]],
        ],
        // P19 to place
        'PREFIXໄປປະຈໍາຢູ່' => [['dots', 'ໄປປະຈໍາຢູ່', $eavGet('relocate_to')]],
        // P20 closing paragraph (org name repeated)
        'PREFIXດັ່ງນັ້ນ' => [['dots', 'ຊື່ອົງການຈັດຕັ້ງ', $eavGet('relocate_org_name')]],
    ];
                    } elseif ((int)$row['doc_type_id'] === 1 && (int)$row['doc_item_id'] === 8 && ($row['template_variant'] ?? '') === 'short') {
    // ຊີວະປະຫວັດຫຍໍ້ (SHORT biography, doc_type 1, item 8, template 01-08-ປະຫວັດຫຍໍ້)
    // Condensed one-page: self profile spans 2 long paragraphs (P6/P7), father P14,
    // mother P15 (both also start with ຊື່ ແລະ ນາມສະກຸນ -> occurrence-aware).
    // Sibling/history blank lines stay for handwriting.
    $eavGet = function($k) use ($eav) { return trim((string)($eav[$k] ?? '')); };
    $bioName = trim($nameTitle . ' ' . $row['first_name'] . ' ' . $row['last_name']);
    $ageOf = function($dob) { return $dob ? floor((time() - strtotime($dob)) / 31536000) : ''; };
    $opsByAnchor = [
        // P6 self name line / P14 father / P15 mother (all start ຊື່ ແລະ ນາມສະກຸນ)
        'PREFIXຊື່ ແລະ ນາມສະກຸນ' => [
            [['dots', 'ຊື່ ແລະ ນາມສະກຸນ:', $bioName],
             ['dots', 'ວັນ,ເດືອນ,ປີເກີດ', $row['dob'] ? date('d/m/Y', strtotime($row['dob'])) : ''],
             ['dots', 'ບ້ານເກີດ', $row['birth_village'] ?? $birthVillageName],
             ['dots', 'ເມືອງ', $row['birth_district'] ?? $birthDistrictName],
             ['dots', 'ແຂວງ', $row['birth_province'] ?? $birthProvinceName],
             ['dots', 'ເຊື້ອຊາດ', $originName],
             ['dots', 'ສັນຊາດ', $nationalityName]],
            [['dots', 'ຊື່ ແລະ ນາມສະກຸນພໍ່ :', $eavGet('bio_father_name')],
             ['dots', 'ອາຍຸ', $ageOf($eavGet('bio_father_dob'))],
             ['dots', 'ສັນຊາດ', $eavGet('bio_father_nat')],
             ['dots', 'ເຊື້ອຊາດ', $eavGet('bio_father_eth')],
             ['dots', 'ອາຊີບ', $eavGet('bio_father_job')],
             ['dots', 'ປະຈຸບັນຢູ່ບ້ານ', $eavGet('bio_father_village')],
             ['dots', 'ເມືອງ', $eavGet('bio_father_district')],
             ['dots', 'ແຂວງ', $eavGet('bio_father_province')]],
            [['dots', 'ຊື່ ແລະ ນາມສະກຸນແມ່: :', $eavGet('bio_mother_name')],
             ['dots', 'ອາຍຸ', $ageOf($eavGet('bio_mother_dob'))],
             ['dots', 'ສັນຊາດ', $eavGet('bio_mother_nat')],
             ['dots', 'ເຊື້ອຊາດ', $eavGet('bio_mother_eth')],
             ['dots', 'ອາຊີບ', $eavGet('bio_mother_job')],
             ['dots', 'ປະຈຸບັນຢູ່ບ້ານ', $eavGet('bio_mother_village')],
             ['dots', 'ເມືອງ', $eavGet('bio_mother_district')],
             ['dots', 'ແຂວງ', $eavGet('bio_mother_province')]],
        ],
        // P7 self second paragraph (ethnicity ... province) — one long line
        'PREFIXຊົນເຜົ່າ' => [
            [['dots', 'ຊົນເຜົ່າ', $ethnicityName],
             ['dots', 'ສາສະໜາ', $religionName],
             ['dots', 'ສຳມະໂນຄົວເລກທີ', $eavGet('bio_family_book_no')],
             ['dots', 'ລົງວັນທີ', fmtDate($eavGet('bio_family_book_date'))],
             ['dots', 'ບັດປະຈຳຕົວເລກທີ', $row['id_card']],
             ['dots2', 'ລົງວັນທີ', fmtDate($row['id_card_issue_date'])],
             ['dots', 'ລະດັບການສຶກສາ', $eavGet('bio_educ_upper')],
             ['dots', 'ລະດັບວິຊາສະເພາະ', $eavGet('bio_special')],
             ['dots', 'ອາຊີບປະຈຸບັນ', $occupationName],
             ['dots', 'ບ່ອນປະຈຳການ', $eavGet('bio_workplace')],
             ['dots', 'ໜ້າທີ່ຮັບຜິດຊອບ', $eavGet('bio_position')],
             ['dots', 'ວັນ/ເດືອນ/ປີສັງກັດລັດ', fmtDate($eavGet('bio_party_date'))],
             ['dots', 'ປະຈຸບັນຢູ່ບ້ານ', $row['village_name']],
             ['dots', 'ເມືອງ', $row['district_name'] ?? ''],
             ['dots', 'ແຂວງ', $row['province_name'] ?? '']],
        ],
        // P24 signature place + date
        'PREFIXທີ່' => [
            [['dots', 'ທີ່', $row['province_name'] ?? ''], ['dots', 'ວັນທີ', $today]],
        ],
    ];
                    } elseif ((int)$row['doc_type_id'] === 1 && (int)$row['doc_item_id'] === 8) {
                        // ຊີວະປະຫວັດທົ່ວໄປ (biography / CV form, doc_type 1, item 8)
                        // Applicant profile from citizen record; family/spouse/work/education from EAV.
                        // Free-text dotted lines and internal tables (history/training/awards/discipline/
                        // children) are left blank for handwriting. Identical ຊື່ ແລະ ນາມສະກຸນ /
                        // ວັນ, ເດືອນ, ປີເກີດ / ອາຊີບປະຈຸບັນ / ບ້ານຢູ່ປະຈຸບັນ rows -> occurrence-aware.
                        $eavGet = function($k) use ($eav) { return trim((string)($eav[$k] ?? '')); };
                        $bioName = trim($nameTitle . ' ' . $row['first_name'] . ' ' . $row['last_name']);
                        $d = $row['dob'] ? date('d', strtotime($row['dob'])) : '';
                        $m = $row['dob'] ? date('m', strtotime($row['dob'])) : '';
                        $y = $row['dob'] ? date('Y', strtotime($row['dob'])) : '';
                        $opsByAnchor = [
                        // P11 self name / P252 father / P259 mother / P270 spouse / P326 sp father / P330 sp mother
                        'PREFIXຊື່ ແລະ ນາມສະກຸນ' => [
                        [['dots', 'ຊື່ ແລະ ນາມສະກຸນ', $bioName]],
                        [['dots', 'ຊື່ ແລະ ນາມສະກຸນ ພໍ່', $eavGet('bio_father_name')]],
                        [['dots', 'ຊື່ ແລະ ນາມສະກຸນຂອງ ແມ່', $eavGet('bio_mother_name')]],
                        [['dots', 'ຊື່ ແລະ ນາມສະກຸນຂອງ ຜົວ ຫຼື ເມຍ', $eavGet('bio_spouse_name')]],
                        [['dots', 'ຊື່ ແລະ ນາມສະກຸນ ພໍ່ ຂອງ ຜົວ ຫຼື ເມຍ', $eavGet('bio_sp_father_name')]],
                        [['dots', 'ຊື່ ແລະ ນາມສະກຸນ ແມ່ຂອງ ຜົວ ຫຼື ເມຍ', $eavGet('bio_sp_mother_name')]],
                        ],
                        // P12 self DOB (day/month/year) + phone (specific anchor BEFORE the generic ວັນ,...)
                        'PREFIXວັນ, ເດືອນ, ປີເກີດ, ວັນທີ' => [
                        [['dots', 'ວັນທີ', $d], ['dots2', 'ເດືອນ', $m], ['dots2', 'ປີ', $y], ['dots', 'ເບີໂທລະສັບ', $row['phone']]],
                        ],
                        // P253 father / P260 mother / P271 spouse / P327 sp father / P331 sp mother DOB+nat+eth+rel
                        'PREFIXວັນ, ເດືອນ, ປີເກີດ' => [
                        [['dots', 'ວັນ, ເດືອນ, ປີເກີດ', fmtDate($eavGet('bio_father_dob'))], ['dots', 'ສັນຊາດ', $eavGet('bio_father_nat')], ['dots', 'ຊົນເຜົ່າ', $eavGet('bio_father_eth')], ['dots', 'ສາສະໜາ', $eavGet('bio_father_rel')]],
                        [['dots', 'ວັນ, ເດືອນ, ປີເກີດ', fmtDate($eavGet('bio_mother_dob'))], ['dots', 'ສັນຊາດ', $eavGet('bio_mother_nat')], ['dots', 'ຊົນເຜົ່າ', $eavGet('bio_mother_eth')], ['dots', 'ສາສະໜາ', $eavGet('bio_mother_rel')]],
                        [['dots', 'ວັນ,ເດືອນ, ປີເກີດ', fmtDate($eavGet('bio_spouse_dob'))], ['dots', 'ສັນຊາດ', $eavGet('bio_spouse_nat')], ['dots', 'ຊົນເຜົ່າ', $eavGet('bio_spouse_eth')], ['dots', 'ສາສະໜາ', $eavGet('bio_spouse_rel')]],
                        [['dots', 'ວັນ,ເດືອນ,ປີເກີດ', fmtDate($eavGet('bio_sp_father_dob'))], ['dots', 'ສັນຊາດ', $eavGet('bio_sp_father_nat')], ['dots', 'ຊົນເຜົ່າ', $eavGet('bio_sp_father_eth')], ['dots', 'ສາສະໜາ', $eavGet('bio_sp_father_rel')]],
                        [['dots', 'ວັນ, ເດືອນ, ປີເກີດ', fmtDate($eavGet('bio_sp_mother_dob'))], ['dots', 'ສັນຊາດ', $eavGet('bio_sp_mother_nat')], ['dots', 'ຊົນເຜົ່າ', $eavGet('bio_sp_mother_eth')], ['dots', 'ສາສະໜາ', $eavGet('bio_sp_mother_rel')]],
                        ],
                        // P13 birth place
                        'PREFIXບ້ານເກີດ' => [
                        [['dots', 'ບ້ານເກີດ', $row['birth_village'] ?? $birthVillageName], ['dots', 'ເມືອງ', $row['birth_district'] ?? $birthDistrictName], ['dots', 'ແຂວງ', $row['birth_province'] ?? $birthProvinceName]],
                        ],
                        // P14 class / nationality / ethnicity / religion
                        'PREFIXຊົນຊັ້ນ' => [
                        [['dots', 'ຊົນຊັ້ນ', $eavGet('bio_class')], ['dots', 'ສັນຊາດ', $nationalityName], ['dots', 'ຊົນຜົ່າ', $ethnicityName], ['dots', 'ສາສະໜາ', $religionName]],
                        ],
                        // P15 current address
                        'PREFIXບ່ອນຢູ່ປະຈຸບັນ' => [
                        [['dots', 'ບ້ານ', $row['village_name']], ['dots', 'ເມືອງ', $row['district_name'] ?? ''], ['dots', 'ແຂວງ', $row['province_name'] ?? '']],
                        ],
                        // P16 family book
                        'PREFIXສຳມະໂນຄົວເລກທີ່' => [
                        [['dots', 'ສຳມະໂນຄົວເລກທີ່', $eavGet('bio_family_book_no')], ['dots', 'ລົງວັນທີ', fmtDate($eavGet('bio_family_book_date'))], ['dots', 'ອອກໃຫ້ທີ່', $eavGet('bio_family_book_at')]],
                        ],
                        // P17 ID card
                        'PREFIXບັດປະຈຳຕົວເລກທີ' => [
                        [['dots', 'ບັດປະຈຳຕົວເລກທີ', $row['id_card']], ['dots', 'ລົງວັນທີ', fmtDate($row['id_card_issue_date'])], ['dots', 'ອອກໃຫ້ທີ່', $eavGet('bio_id_issue_at')]],
                        ],
                        // P18 workplace
                        'PREFIXບ່ອນປະຈຳການ' => [
                        [['dots', 'ບ່ອນປະຈຳການ', $eavGet('bio_workplace')]],
                        ],
                        // P19 revolution + party org entry
                        'PREFIXວັນ, ເດືອນ, ປີເຂົ້າການປະຕິວັດ' => [
                        [['dots', 'ວັນ, ເດືອນ, ປີເຂົ້າການປະຕິວັດ', fmtDate($eavGet('bio_rev_date'))], ['dots', 'ວັນ, ເດືອນ, ປີເຂົ້າສັງກັດລັດ', fmtDate($eavGet('bio_party_date'))]],
                        ],
                        // P20 position / P21 salary
                        'PREFIXໜ້າທີ່ຮັບຜິດຊອບ' => [['dots', 'ໜ້າທີ່ຮັບຜິດຊອບ', $eavGet('bio_position')]],
                        'PREFIXຊັ້ນ, ຂັ້ນເງິນເດືອນ' => [['dots', 'ຊັ້ນ, ຂັ້ນເງິນເດືອນ', $eavGet('bio_salary')]],
                        // P23-27 organizations / party
                        'PREFIXວັນ, ເດືອນ, ປີເຂົ້າອົງການຈັດຕັ້ງຊາວໜຸ່ມ' => [['dots', 'ຢູ່ໃສ', $eavGet('bio_youth_at')]],
                        'PREFIXວັນ, ເດືອນ, ປີເຂົ້າອົງການສະຫະພັນແມ່ຍິງ' => [['dots', 'ຢູ່ໃສ', $eavGet('bio_women_at')]],
                        'PREFIXວັນ, ເດືອນ, ປີເຂົ້າອົງການຈັດຕັ້ງກຳມະບານ' => [['dots', 'ຢູ່ໃສ', $eavGet('bio_union_at')]],
                        'PREFIXວັນ, ເດືອນ, ປີເຂົ້າພັກສຳຮອງ' => [['dots', 'ວັນ, ເດືອນ, ປີເຂົ້າພັກສົມບູນ', fmtDate($eavGet('bio_party_full'))]],
                        'PREFIXຜູ້ຮັບເຂົ້າພັກ' => [['dots', 'ຜູ້ຮັບເຂົ້າພັກຊື່ຫຍັງ', $eavGet('bio_recruiter')], ['dots', 'ດຽວນີ້ເຮັດຫຍັງ, ຢູ່ໃສ', $eavGet('bio_recruiter_now')]],
                        // P29 education
                        'PREFIXການສຶກສາຈົບປະຖົມ' => [['dots', 'ຈົບປະຖົມ', $eavGet('bio_educ_primary')], ['dots', 'ຈົບມໍຕົ້ນ', $eavGet('bio_educ_lower')], ['dots', 'ຈົບມໍປາຍ', $eavGet('bio_educ_upper')]],
                        // P35-38 languages
                        'PREFIXພາສາອັງກິດ' => [['dots', 'ພາສາອັງກິດ', $eavGet('bio_lang_en')]],
                        'PREFIXພາສາຝລັ່ງ' => [['dots', 'ພາສາຝລັ່ງ', $eavGet('bio_lang_fr')]],
                        'PREFIXພາສາລັດເຊຍ' => [['dots', 'ພາສາລັດເຊຍ', $eavGet('bio_lang_ru')]],
                        'PREFIXພາສາອື່ນໆ' => [['dots', 'ພາສາອື່ນໆ', $eavGet('bio_lang_other')]],
                        // P254 father / P261 mother / P329 sp father / P333 sp mother village/district/province
                        'PREFIXບ້ານຢູ່ປະຈຸບັນ' => [
                        [['dots', 'ບ້ານຢູ່ປະຈຸບັນ', $eavGet('bio_father_village')], ['dots', 'ເມືອງ', $eavGet('bio_father_district')], ['dots', 'ແຂວງ', $eavGet('bio_father_province')]],
                        [['dots', 'ບ້ານຢູ່ປະຈຸບັນ', $eavGet('bio_mother_village')], ['dots', 'ເມືອງ', $eavGet('bio_mother_district')], ['dots', 'ແຂວງ', $eavGet('bio_mother_province')]],
                        [['dots', 'ບ້ານຢູ່ປະຈຸບັນ', $eavGet('bio_sp_father_village')], ['dots', 'ເມືອງ', $eavGet('bio_sp_father_district')], ['dots', 'ແຂວງ', $eavGet('bio_sp_father_province')]],
                        [['dots', 'ບ້ານຢູ່ປະຈຸບັນ', $eavGet('bio_sp_mother_village')], ['dots', 'ເມືອງ', $eavGet('bio_sp_mother_district')], ['dots', 'ແຂວງ', $eavGet('bio_sp_mother_province')]],
                        ],
                        // P255 father / P262 mother / P272 spouse / P328 sp father / P332 sp mother occupation (+workplace for spouse/in-law)
                        'PREFIXອາຊີບປະຈຸບັນ' => [
                        [['dots', 'ອາຊີບປະຈຸບັນ', $eavGet('bio_father_job')]],
                        [['dots', 'ອາຊີບປະຈຸບັນ', $eavGet('bio_mother_job')]],
                        [['dots', 'ອາຊີບປະຈຸບັນ', $eavGet('bio_spouse_job')], ['dots', 'ບ່ອນປະຈຳການ', $eavGet('bio_spouse_workplace')]],
                        [['dots', 'ອາຊີບປະຈຸບັນ', $eavGet('bio_sp_father_job')], ['dots', 'ບ່ອນປະຈຳການ', $eavGet('bio_sp_father_at')]],
                        [['dots', 'ອາຊີບປະຈຸບັນ', $eavGet('bio_sp_mother_job')], ['dots', 'ບ່ອນປະຈຳການ', $eavGet('bio_sp_mother_at')]],
                        ],
                        // P273 children count
                        'PREFIXມີລູກ' => [['dots', 'ມີລູກນຳກັນຈັກຄົນ', $eavGet('bio_children_count')]],
                        // P390 health
                        'PREFIXສຸຂະພາບ' => [['dots', 'ສຸຂະພາບ', $eavGet('bio_health')]],
                        // P393 signature province + date
                        'PREFIXແຂວງ' => [['dots', 'ແຂວງ', $row['province_name'] ?? ''], ['dots', 'ວັນທີ', date('d')], ['dots', 'ເດືອນ', date('m')], ['dots', 'ປີ', date('Y')]],
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
$occurrence = []; // per-anchor occurrence counter (identical continuation lines)
foreach ($paras as $p) {
  $full = '';
  foreach (wRuns($p) as $r) $full .= runText($r);
  $trimmed = ltrim($full);
  foreach ($opsByAnchor as $anchor => $ops) {
    $isPrefix = (strpos($anchor, 'PREFIX') === 0);
    if ($isPrefix) {
      if (strpos($trimmed, substr($anchor, 6)) !== 0) continue; // line-start match only
    } else {
      if (strpos($full, $anchor) === false) continue;
    }
    // occurrence-aware: ops may be a list of op-lists (one per identical line)
    $opsList = $ops;
    $usedOcc = null;
    if (isset($ops[0]) && is_array($ops[0]) && isset($ops[0][0]) && is_array($ops[0][0])) {
      $i = $occurrence[$anchor] ?? 0;
      $opsList = $ops[$i] ?? end($ops);
      $usedOcc = $i;
    }
    $anyFilled = false;
    foreach ($opsList as $op) {
      if ($op[0] === 'dots')   { if (fillDots($p, $op[1], $op[2])) $anyFilled = true; }
      else if ($op[0] === 'dotspan') { if (fillDotSpan($p, $op[1], $op[2])) $anyFilled = true; }
      else if ($op[0] === 'dots2') { if (fillDotsN($p, $op[1], $op[2], 2)) $anyFilled = true; }
      else if ($op[0] === 'phrase') { replacePhrase($p, $op[1], $op[2]); $anyFilled = true; }
      else if ($op[0] === 'name') fillNameLine($p, $op[1], $op[2]);
    }
    // only consume an occurrence slot when the paragraph actually got filled
    // (prevents heading lines that match the PREFIX from stealing a slot)
    if ($usedOcc !== null && $anyFilled) { $occurrence[$anchor] = $usedOcc + 1; }
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
        // collapse doubled commas that fillDots produced (template comma + fillDots ', ' suffix)
        $c->textContent = preg_replace('/,\s*,/', ',', $c->textContent);
        // tidy ບ້ານ , -> ບ້ານ when the value itself was empty
        $c->textContent = preg_replace('/\s+,\s*$/u', '', $c->textContent);
        // remove comma that precedes a slash (doc-ref fraction artifact: 523, / -> 523 /)
        $c->textContent = preg_replace('/,\s*\//', '/', $c->textContent);
      }
    }
  }
}
// remove trailing comma+space at the very end of each paragraph (template artifact)
foreach ($paras as $p) {
  foreach (wRuns($p) as $r) {
    $last = $r->lastChild;
    if ($last && $last->localName === 't') {
      $last->textContent = preg_replace('/,\s*$/u', '', $last->textContent);
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
$fname = ($row['doc_type_id'] == 5) ? 'ບົດບັນທຶກການຢ່າຮ້າງ_'
        : (($row['doc_type_id'] == 4) ? 'ໃບແຈ້ງເສຍຫາຍ_'
        : (($row['doc_type_id'] == 2) ? 'ໃບຄຳຮ້ອງຂໍຈົດທະບຽນແຕ່ງດອງ_'
        : (($row['doc_type_id'] == 14) ? 'ໃບຢັ້ງຢືນຊື້-ຂາຍ ທີ່ດິນ_'
        : ($row['doc_type_id'] == 6
            ? ((int)$row['doc_item_id'] === 53 ? 'ສັນຍາຊື້-ຂາຍ ກຳມະສິດທີ່ດິນ_'
                : ((int)$row['doc_item_id'] === 30 ? 'ໃບສັນຍາເຊົ່າ_'
                    : 'ສັນຍາກູ້ຢືມເງິນ_'))
            : (((int)$row['doc_item_id'] === 5 ? 'ໃບຢັ້ງຢືນສາຍຍາດ_' : ((int)$row['doc_item_id'] === 8 ? 'ຊີວະປະຫວັດທົ່ວໄປ_' : ((int)$row['doc_item_id'] === 54 ? 'ໃບຍົກຍ້າຍ_' : ((int)$row['doc_item_id'] === 55 ? 'ໃບຢັ້ງຢືນການເສຍຫາຍ_' : ((int)$row['doc_item_id'] === 56 ? 'ໃບຢັ້ງຢືນຄວາມເປັນໂສດ_' : ((int)$row['doc_item_id'] === 57 ? 'ໃບຢັ້ງຢືນລູກທາງ (ລູກຊູ້)_' : 'ໃບຢັ້ງຢືນທີ່ຢູ່_'))))))))))) . ($row['doc_no'] ?? $doc_id) . '.docx';
$ascii = ($row['doc_type_id'] == 5) ? 'divorce_record_'
        : (($row['doc_type_id'] == 4) ? 'damage_notify_'
        : (($row['doc_type_id'] == 2) ? 'marriage_request_'
        : (($row['doc_type_id'] == 14) ? 'land_sale_'
        : ($row['doc_type_id'] == 6
            ? ((int)$row['doc_item_id'] === 53 ? 'land_sale_contract_'
                : ((int)$row['doc_item_id'] === 30 ? 'lease_contract_'
                    : 'loan_contract_'))
            : (((int)$row['doc_item_id'] === 5 ? 'kinship_cert_' : ((int)$row['doc_item_id'] === 8 ? 'biography_' : ((int)$row['doc_item_id'] === 54 ? 'relocation_cert_' : ((int)$row['doc_item_id'] === 55 ? 'damage_cert_' : ((int)$row['doc_item_id'] === 56 ? 'single_status_cert_' : ((int)$row['doc_item_id'] === 57 ? 'foster_child_cert_' : 'residence_cert_'))))))))))) . ($row['doc_no'] ?? $doc_id) . '.docx';

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
