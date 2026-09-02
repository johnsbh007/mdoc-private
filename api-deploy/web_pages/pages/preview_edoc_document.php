<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require_once '../connect.php';

$doc_id = (int)($_GET['doc_id'] ?? 0);
if ($doc_id <= 0) { die('Missing doc_id'); }

// ── load doc + citizen + residence ───────────────────────────────────
$d = $conn->prepare("SELECT d.*, c.*, v.name_lo AS village_name, f.name_lo AS taseng_name,
                            g.name_lo AS district_name, h.name_lo AS province_name,
                            i.name_lo AS item_name, t.name_lo AS type_name, i.doc_type_id
                     FROM edoc_document d
                     JOIN edoc_citizen c ON d.citizen_id = c.id
                     JOIN edoc_doc_item i ON d.doc_item_id = i.id
                     JOIN edoc_doc_type t ON i.doc_type_id = t.id
                     LEFT JOIN edoc_village v ON c.village_id = v.id
                     LEFT JOIN edoc_taseng f ON c.taseng_id = f.id
                     LEFT JOIN edoc_district g ON c.district_id = g.id
                     LEFT JOIN edoc_province h ON c.province_id = h.id
                     WHERE d.id = ?");
$d->execute([$doc_id]);
$row = $d->fetch(PDO::FETCH_ASSOC);
if (!$row) { die('Document not found'); }
if (!in_array((int)$row['doc_type_id'], [1, 2, 4, 5, 6, 14], true)) { die('Preview available for supported document types only'); }

// ── type 14 (land sell-purchase letter): render a data-summary preview ──
// The authoritative output is the generated .docx; this preview lets the officer
// verify the captured fields before generating. Rendered from the EAV values.
if ((int)$row['doc_type_id'] === 14) {
    $eav = [];
    $ev = $conn->prepare("SELECT f.field_label, fd.field_value
                          FROM edoc_form_data fd
                          JOIN edoc_doc_field f ON f.id = fd.field_id
                          WHERE fd.doc_id = ? ORDER BY f.sort_order");
    $ev->execute([$doc_id]);
    foreach ($ev->fetchAll(PDO::FETCH_ASSOC) as $r) { if ($r['field_value'] !== '' && $r['field_value'] !== null) { $eav[] = $r; } }
    require '../components/layout.php';
    ?>
    <style>
    .ls-card { max-width:720px; margin:0 auto; background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.07); padding:22px 26px; }
    .ls-card h6 { border-bottom:2px solid #044482; padding-bottom:8px; margin-bottom:14px; }
    .ls-row { display:flex; justify-content:space-between; gap:16px; padding:6px 0; border-bottom:1px solid #eef2f7; font-size:.92rem; }
    .ls-row .k { color:#64748b; flex:0 0 45%; }
    .ls-row .v { font-weight:600; text-align:right; }
    .ls-empty { color:#94a3b8; font-style:italic; }
    </style>
    <div class="container-fluid py-3">
      <div class="ls-card">
        <h6><i class="fas fa-file-contract text-primary"></i> ໃບຢັ້ງຢືນຊື້-ຂາຍ ທີ່ດິນ — ສະຫຼຸບຂໍ້ມູນ (Land sale — data summary)</h6>
        <div class="ls-row"><span class="k">ເລກທີ່ຄຳຮ້ອງ / Doc no</span><span class="v"><?= htmlspecialchars($row['doc_no']) ?></span></div>
        <div class="ls-row"><span class="k">ຜູ້ຂາຍ (ຜູ້ຖືບັດ) / Seller (applicant)</span><span class="v"><?= htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></span></div>
        <?php
        // buyer shown from the citizen record (buyer_id -> edoc_citizen)
        $buyerName = '';
        if (!empty($row['buyer_id'])) {
            $bs = $conn->prepare("SELECT title, first_name, last_name, id_card, dob FROM edoc_citizen WHERE id=?");
            $bs->execute([$row['buyer_id']]);
            $buyer = $bs->fetch(PDO::FETCH_ASSOC);
            if ($buyer) {
                $buyerName = trim(($buyer['title'] ?? '') . ' ' . ($buyer['first_name'] ?? '') . ' ' . ($buyer['last_name'] ?? ''));
                $buyerIdCard = $buyer['id_card'] ?? '';
                $buyerDob = $buyer['dob'] ?? '';
                if ($buyerDob) { $buyerName .= ' (ອາຍຸ ' . floor((time()-strtotime($buyerDob))/31536000) . ')'; }
                if ($buyerIdCard) { $buyerName .= ' — ບັດ ' . $buyerIdCard; }
            }
        }
        ?>
        <div class="ls-row"><span class="k">ຜູ້ຊື້ / Buyer</span><span class="v"><?= htmlspecialchars($buyerName ?: '—') ?></span></div>
        <?php if (!$eav): ?>
          <div class="ls-empty mt-3">ຍັງບໍ່ມີຂໍ້ມູນສະເພາະເອກະສານ (No document fields captured yet)</div>
        <?php else: foreach ($eav as $f): ?>
          <div class="ls-row"><span class="k"><?= htmlspecialchars($f['field_label']) ?></span><span class="v"><?= htmlspecialchars($f['field_value']) ?></span></div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php require '../components/layout_footer.php'; ?>
    <?php exit; ?>
    <?php
}

// ── item 5 (kinship certificate ໃບຢັ້ງຢືນສາຍຍາດ): data-summary preview ──
if ((int)$row['doc_type_id'] === 1 && (int)($row['doc_item_id'] ?? 0) === 5) {
    // ໃບຢັ້ງຢືນສາຍຍາດ (Kinship certificate, item 5) — render template as HTML
    $eav = [];
    $ev = $conn->prepare("SELECT f.field_name, fd.field_value
                          FROM edoc_form_data fd
                          JOIN edoc_doc_field f ON f.id = fd.field_id
                          WHERE fd.doc_id = ?");
    $ev->execute([$doc_id]);
    foreach ($ev->fetchAll(PDO::FETCH_ASSOC) as $r) { $eav[$r['field_name']] = $r['field_value']; }
    $eavGet = function($k) use ($eav) { return trim((string)($eav[$k] ?? '')); };
    $template='/var/www/html/edoc/Doc_files/01-05-ໃບຢັ້ງຢືນສາຍຍາດ.docx';
    if (!file_exists($template)) { die('Template not found'); }
    $xml = (function() use ($template){ $z=new ZipArchive(); $z->open($template); $s=$z->getFromName('word/document.xml'); $z->close(); return $s; })();
    $dom=new DOMDocument(); $dom->loadXML($xml);
    $ns='http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    require '../components/layout.php';
    ?><style>
    .preview-paper { background:#fff; border:1px solid #ddd; border-radius:8px; box-shadow:0 4px 18px rgba(0,0,0,.09); max-width:794px; margin:0 auto 20px; padding:44px 52px; font-family:'Noto Sans Lao','Phetsarath OT',serif; font-size:15px; line-height:1.9; }
    .preview-paper p { margin:0 0 4px; }
    .pv-docref { display:flex; justify-content:space-between; align-items:baseline; }
    .pv-docref .pv-docref-left { text-align:left; }
    .pv-docref .pv-docref-right { text-align:right; white-space:nowrap; font-weight:600; }
    .pv-opening { clear:both; text-indent:1.27cm; }
    .pv-title { text-align:center; font-weight:bold; font-size:16px; margin:16px 0 20px !important; }
    .pv-emblem { text-align:center; margin:0 0 6px; }
    .pv-emblem img { width:1.5cm; height:auto; }
    .pv-sign { margin:20px 0 0 50% !important; text-align:center; font-size:14px; }
    .pv-date { text-align:right; padding-right:36pt; margin:20px 0 0; }
    .pv-signrow { display:flex; justify-content:space-between; align-items:flex-start; margin:0; font-size:14px; }
    .pv-signcol-left { padding-left:36pt; }
    .pv-signcol-left .title { text-align:left; margin:0; }
    .pv-signcol-left .name { text-align:center; margin:0; }
    .pv-signcol-right { text-align:right; padding-right:36pt; }
    .pv-note { background:#fff8e1; border:1px solid #eed99a; color:#8a6d00; border-radius:8px; padding:10px 16px; max-width:820px; margin:0 auto 14px; font-size:.9rem; }
    .pv-actions { max-width:820px; margin:0 auto 16px; }
    @media print {
      @page { size: A4; margin: 15mm 17mm; }
      body { background:#fff !important; }
      .preview-paper { max-width:100%; border:none; box-shadow:none; border-radius:0; padding:0; margin:0; }
      .pv-note, .pv-actions, .breadcrumb { display:none !important; }
      .preview-paper p { break-inside: avoid; }
    }
    </style>
    <div class="content-wrapper p-3">
      <nav class="breadcrumb mb-2">
        <ol class="breadcrumb mb-2">
          <li class="breadcrumb-item"><a href="list_edoc_document.php">ບໍລິການເອກະສານ</a></li>
          <li class="breadcrumb-item"><a href="review_edoc_document.php?doc_id=<?= $doc_id ?>">ຈັດການ</a></li>
          <li class="breadcrumb-item active">ສະແດງຕົວຢ່າງ (Preview)</li>
        </ol>
      </nav>
      <div class="pv-actions text-center">
        <a class="btn btn-outline-secondary" href="review_edoc_document.php?doc_id=<?= $doc_id ?>"><i class="fas fa-arrow-left"></i> ກັບຄືນ (Back to review)</a>
        <button class="btn btn-outline-primary ms-2" onclick="window.print()"><i class="fas fa-print"></i> ພິມ (Print)</button>
        <a class="btn btn-outline-success ms-2" target="_blank" href="../generate/generate_edoc_document.php?doc_id=<?= $doc_id ?>"><i class="fas fa-file-word"></i> ດາວໂຫລດ Word (Download)</a>
      </div>
      <div class="pv-note"><i class="fas fa-info-circle"></i> ຕົວຢ່າງສະແດງຕາມແບບຟອມທາງການ (Preview of the official form)</div>
      <div class="preview-paper">
    <?php
    // ── fill the template ──
    // NOTE: wRuns/runText/setRunText/spliceAt/replacePhrase/fillDots/fmtDate/resolveName
    // are declared top-level later in this file (residence fall-through, ~line 740+)
    // and PHP hoists top-level functions — redeclaring them here would fatal.
    $title=($row['gender']==='M')?'ທ້າວ':(($row['gender']==='F')?'ນາງ':''); $today=date('d/m/Y');
    $applicantName = trim($title . ' ' . $row['first_name'] . ' ' . $row['last_name']);
    $originName=resolveName($conn,'edoc_country',$row['origin']); $nationalityName=resolveName($conn,'edoc_country',$row['nationality']);
    $occupationName=resolveName($conn,'edoc_occupation',$row['occupation']);
    $paras=$dom->getElementsByTagNameNS($ns,'p');
    $occurrence=[];
    // Resolve occupation codes / village ids to display names (EAV now stores
    // occupation CODE + village ID). Mirrors the generator's kinship branch.
    $kinOcc = function($v) use ($conn) { return $v===''?'':resolveName($conn,'edoc_occupation',$v); };
    $kinVil = function($v) use ($conn) {
      if ($v===''||$v===null) return '';
      if (is_numeric($v)) { $st=$conn->prepare("SELECT name_lo FROM edoc_village WHERE id=?"); $st->execute([(int)$v]); $n=$st->fetchColumn(); return $n!==false?$n:''; }
      return $v;
    };
    $kinChain = function($v) use ($conn) {
      $empty=['','','','']; if($v===''||$v===null||!is_numeric($v)) return $empty;
      $st=$conn->prepare("SELECT v.name_lo,t.name_lo,d.name_lo,p.name_lo FROM edoc_village v JOIN edoc_taseng t ON v.tasseng_id=t.id JOIN edoc_district d ON t.district_id=d.id JOIN edoc_province p ON d.province_id=p.id WHERE v.id=?");
      $st->execute([(int)$v]); $r=$st->fetch(PDO::FETCH_NUM); return $r?:$empty;
    };
    // Spouse GEO: derive ບ້ານ/ເມືອງ/ແຂວງ from village chain (spouse_district/province deactivated).
    $spV = $eavGet('spouse_village'); $spVName=$spV; $spDName=$eavGet('spouse_district'); $spPName=$eavGet('spouse_province');
    if (is_numeric($spV)) { $sc=$kinChain($spV); $spVName=$sc[0]; $spDName=$sc[2]; $spPName=$sc[3]; }
    foreach($paras as $p){
      $full=''; foreach(wRuns($p) as $r)$full.=runText($r);
      $trimmed=ltrim($full);
      // Fill ops (matching generator's item-5 branch)
      if(strpos($full,'ວັນທີ')!==false && strpos($full,'ຊື່')===false && strpos($full,'ອອກ')===false){
        fillDots($p,'ວັນທີ',$today);
      }
      // P7 applicant: entire name line
      if(strpos($full,'ຊື່ ແລະ ນາມສະກຸນຂອງເຈົ້າຂອງສິດ')!==false){
        replacePhrase($p,'ຊື່ ແລະ ນາມສະກຸນຂອງເຈົ້າຂອງສິດນໍາໃຊ້ທີ່ດິນ',$applicantName);
        fillDots($p,'ວັນ,ເດືອນ,ປີເກີດ',fmtDate($row['dob']));
        fillDots($p,'ສັນຊາດ',$nationalityName);
        fillDots($p,'ອາຊີບ',$occupationName);
        fillDots($p,'ມີບັດປະຈຳຕົວເລກທີ',$row['id_card']);
        fillDots($p,'ລົງວັນທີ',fmtDate($row['id_card_issue_date']));
        fillDots($p,'ປະຈຸບັນຢູ່ໜ່ວຍ',$row['unit_no']);
        fillDots($p,'ບ້ານ',$row['village_name']);
        fillDots($p,'ເມືອງ',$row['district_name'] ?? '');
        fillDots($p,'ແຂວງ',$row['province_name'] ?? '');
      }
      // P9/P16 father (own/spouse) — occurrence-aware
      if(strpos($full,'ຊື່ພໍ່')!==false && strpos($full,'ຊື່ແມ່')===false){
        $i=$occurrence['father']??0;
        $names=[$eavGet('father_name'),$eavGet('sp_father_name')];
        $ages=[$eavGet('father_age'),$eavGet('sp_father_age')];
        $occs=[$eavGet('father_occupation'),$eavGet('sp_father_occupation')];
        $vils=[$eavGet('father_village'),$eavGet('sp_father_village')];
        if(isset($names[$i])){
          fillDots($p,'ຊື່ພໍ່',$names[$i]); fillDots($p,'ອາຍຸ',$ages[$i]);
          fillDots($p,'ອາຊີບ',$kinOcc($occs[$i])); fillDots($p,'ບ້ານ',$kinVil($vils[$i]));
        }
        $occurrence['father']=($i+1);
      }
      // P10/P17 mother (own/spouse) — occurrence-aware
      if(strpos($full,'ຊື່ແມ່')!==false){
        $i=$occurrence['mother']??0;
        $names=[$eavGet('mother_name'),$eavGet('sp_mother_name')];
        $ages=[$eavGet('mother_age'),$eavGet('sp_mother_age')];
        $occs=[$eavGet('mother_occupation'),$eavGet('sp_mother_occupation')];
        $vils=[$eavGet('mother_village'),$eavGet('sp_mother_village')];
        if(isset($names[$i])){
          fillDots($p,'ຊື່ແມ່',$names[$i]); fillDots($p,'ອາຍຸ',$ages[$i]);
          fillDots($p,'ອາຊີບ',$kinOcc($occs[$i])); fillDots($p,'ບ້ານ',$kinVil($vils[$i]));
        }
        $occurrence['mother']=($i+1);
      }
      // P11-13 siblings (own), P18-20 spouse rels, P22-24 children, P26-28 other rels
      // — 12 rows in document order, same list as the generator's PREFIXຊື່.
      if(strpos($full,'ຊື່.')!==false || strpos($full,'ຊື່')!==false && $full!=='' && strpos($full,'ຊື່ພໍ່')===false && strpos($full,'ຊື່ແມ່')===false && strpos($full,'ຊື່ ແລະ')===false && strpos($full,'./')===false){
        $i=$occurrence['rel']??0;
        $rels=[
          ['sib1_name','sib1_age','sib1_occupation','sib1_village'],
          ['sib2_name','sib2_age','sib2_occupation','sib2_village'],
          ['sib3_name','sib3_age','sib3_occupation','sib3_village'],
          ['spr1_name','spr1_age','spr1_occupation','spr1_village'],
          ['spr2_name','spr2_age','spr2_occupation','spr2_village'],
          ['spr3_name','spr3_age','spr3_occupation','spr3_village'],
          ['child1_name','child1_age','child1_occupation','child1_village'],
          ['child2_name','child2_age','child2_occupation','child2_village'],
          ['child3_name','child3_age','child3_occupation','child3_village'],
          ['rel1_name','rel1_age','rel1_occupation','rel1_village'],
          ['rel2_name','rel2_age','rel2_occupation','rel2_village'],
          ['rel3_name','rel3_age','rel3_occupation','rel3_village'],
        ];
        if(isset($rels[$i])){
          $f=$rels[$i];
          $val=$eavGet($f[0]);
          if($val!==''){ fillDots($p,'ຊື່',$val); fillDots($p,'ອາຍຸ',$eavGet($f[1])); fillDots($p,'ອາຊີບ',$kinOcc($eavGet($f[2]))); fillDots($p,'ບ້ານ',$kinVil($eavGet($f[3]))); }
        }
        $occurrence['rel']=($i+1);
      }
      // P14 spouse
      if(strpos($full,'ຊື່ ແລະ ນາມສະກຸນຂອງຜົວ ຫຼື ເມຍ')!==false){
        fillDots($p,'ຊື່ ແລະ ນາມສະກຸນຂອງຜົວ ຫຼື ເມຍ',$eavGet('spouse_name'));
        fillDots($p,'ວັນ,ເດືອນ,ປີເກີດ',$eavGet('spouse_dob')?fmtDate($eavGet('spouse_dob')):'');
        fillDots($p,'ສັນຊາດ',$eavGet('spouse_nationality'));
        fillDots($p,'ອາຊີບ',$kinOcc($eavGet('spouse_occupation')));
        fillDots($p,'ໜ່ວຍ',$eavGet('spouse_unit'));
        fillDots($p,'ບ້ານ',$spVName);
        fillDots($p,'ເມືອງ',$spDName);
        fillDots($p,'ແຂວງ',$spPName);
      }
      // signature block
      if(strpos($full,'ຕາງໜ້າ')!==false){
        // replace ສີວິໄລ with actual taseng name
        $taseng=$row['taseng_name']??'';
        if($taseng!==''){ $tasengR=preg_replace('/^ຕາແສງ/u','',$taseng); replacePhrase($p,'ສີວິໄລ',trim($tasengR)); }
      }
    }
    // ── extract emblem ──
    $emblemB64='';
    $tmpE=tempnam(sys_get_temp_dir(),'emblem').'.png';
    $ze=new ZipArchive(); if($ze->open($template)===true){ $img=$ze->getFromName('word/media/image1.png'); $ze->close(); if($img!==false)$emblemB64=base64_encode($img); }
    @unlink($tmpE);
    // ── load numbering.xml once: map numId → lvl0 indent (left/hanging twips) ──
    $numIndent=[];
    $ze2=new ZipArchive(); if($ze2->open($template)===true){ $nxml=$ze2->getFromName('word/numbering.xml'); $ze2->close();
      if($nxml!==false){ $nd=new DOMDocument(); @$nd->loadXML($nxml); $nx=new DOMXPath($nd); $nx->registerNamespace('w',$ns);
        $num2abs=[];
        foreach($nx->query('//w:num') as $n){ $id=(int)$n->getAttribute('w:numId'); $a=$nx->query('.//w:abstractNumId',$n)->item(0); if($a)$num2abs[$id]=(int)$a->getAttribute('w:val'); }
        $absInd=[];
        foreach($nx->query('//w:abstractNum') as $a){ $id=(int)$a->getAttribute('w:abstractNumId'); $lvl=$nx->query('./w:lvl',$a)->item(0); if(!$lvl)continue;
          $pPr=$nx->query('./w:pPr',$lvl)->item(0); $ie=$pPr?$nx->query('./w:ind',$pPr)->item(0):null;
          $absInd[$id]=['left'=>$ie?(int)$ie->getAttribute('w:left'):0,'hang'=>$ie?(int)$ie->getAttribute('w:hanging'):0];
        }
        foreach($num2abs as $nid=>$aid) if(isset($absInd[$aid]))$numIndent[$nid]=$absInd[$aid];
      }
    }
    // ── render as HTML ──
    $xpath=new DOMXPath($dom); $xpath->registerNamespace('w',$ns);
    $htmlParas=[]; $imgFound=false;
    $numCnt=[]; // per-numId running counter for Word auto-numbering (lvlText='%1.')
    foreach($paras as $p){
      $text=''; foreach($xpath->query('.//w:t',$p) as $t)$text.=$t->textContent;
      $jc=$xpath->query('.//w:jc',$p)->item(0); $align=$jc?$jc->getAttribute('w:val'):'both';
      $hasEmblem= (!$imgFound) && ($xpath->query('.//*[local-name()="drawing"]',$p)->length>0 || $xpath->query('.//*[local-name()="pict"]',$p)->length>0);
      if($hasEmblem)$imgFound=true;
      $numPrNode=$xpath->query('.//w:numPr',$p)->item(0);
      $numId=0; if($numPrNode){ $nn=$xpath->query('.//w:numId',$numPrNode)->item(0); $numId=$nn?(int)$nn->getAttribute('w:val'):0; }
      // Word auto-number prefix (exact template numbering: each numId restarts at 1)
      $numPrefix='';
      if($numId>0 && isset($numIndent[$numId])){ $numCnt[$numId]=($numCnt[$numId]??0)+1; $numPrefix=$numCnt[$numId].'. '; }
      // clean: dots are fill placeholders → ", "; literal numbers (3., 4./, 5./, 6./) keep their single dot
      $t=preg_replace('/\s*\.{4,}\s*/u', ', ', $text);
      $t=preg_replace('/\s{2,}/',' ',$t); $t=preg_replace('/,\s*,/',',',$t); $t=preg_replace('/\s+,/',',',$t); $t=preg_replace('/,\s+$/','',$t); $t=preg_replace('/^\s+/','',$t);
      $rt='normal';
      if($hasEmblem){ $rt='emblem'; }
      else if($t==='ໃບຢັ້ງຢືນສາຍຍາດ'){ $rt='title'; }
      // signature header row (P33): ຕາງໜ້າ ປະທານ... + ລາຍເຊັນຂອງຜູ້ກ່ຽວ — two columns on one line
      else if(strpos($t,'ລາຍເຊັນ')!==false){ $rt='signrow'; }
      // ປະທານ (P34): sub-line under the LEFT signature column
      else if($t==='ປະທານ'){ $rt='sign'; }
      // date line (P32): ວັນທີ... right-aligned
      else if(strpos($t,'ວັນທີ')===0){ $rt='date'; }
      if($rt==='normal' && $numPrefix!=='') $t=$numPrefix.$t;
      // split the signature row into LEFT (ຕາງໜ່າ...) / RIGHT (ລາຍເຊັນ...) columns
      $sigLeft=''; $sigRight='';
      if($rt==='signrow'){
        $parts=preg_split('/\s{4,}/u', $text, 2);
        $sigLeft=trim(preg_replace('/\s+/u',' ', $parts[0]??''));
        $sigRight=trim(preg_replace('/\s+/u',' ', $parts[1]??''));
        $sigLeft=preg_replace('/\s*\.{4,}\s*/u',', ',$sigLeft);
        $sigRight=preg_replace('/\s*\.{4,}\s*/u',', ',$sigRight);
      }
      // per-paragraph indent + alignment: explicit w:ind wins; else numPr level indent
      // cap first-line indent at 567 twips (1 tab) so the closing statement fits on one line
      $style='';
      $in=$xpath->query('.//w:ind',$p)->item(0);
      if($in){
        $fl=(int)$in->getAttribute('w:firstLine'); $lf=(int)$in->getAttribute('w:left'); $hg=(int)$in->getAttribute('w:hanging');
        if($lf){ $style.='padding-left:'.($lf/20).'pt;'; if($hg)$style.='text-indent:-'.($hg/20).'pt;'; }
        if($fl && !$hg){
          // P30 ຊອບພຽງ and P31 ໝາຍເຫດ: should have no indent (user request)
          if(strpos($t,'ຊອບພຽງ')!==0 && strpos($t,'ໝາຍເຫດ:')!==0)
            $style.='text-indent:'.(min($fl,567)/20).'pt;';
        }
      }
      else if($numId>0 && isset($numIndent[$numId])){
        $L=$numIndent[$numId]['left']; $H=$numIndent[$numId]['hang'];
        if($L){ $style.='padding-left:'.($L/20).'pt;'; }
        if($H){ $style.='text-indent:-'.($H/20).'pt;'; }
      }
      if($align==='center')$style.='text-align:center;';
      else if($align==='right')$style.='text-align:right;';
      $htmlParas[]=['text'=>$t,'rt'=>$rt,'style'=>$style,'left'=>$sigLeft,'right'=>$sigRight];
    }
    // ── emit as HTML, skipping the blank paragraphs between emblem↔country
    // and the single blank before the title (P05) so only one blank line remains ──
    $seenText=false;
    for($i=0;$i<count($htmlParas);$i++){
      $hp=$htmlParas[$i];
      if($hp['rt']==='skip')continue;
      if($hp['rt']==='emblem'&&$emblemB64!==''){ echo '<div class="pv-emblem"><img src="data:image/png;base64,'.$emblemB64.'" alt="ກາແຫ່ງຊາດ"></div>'; }
      else if($hp['rt']==='title'){ echo '<p class="pv-title">'.htmlspecialchars($hp['text']).'</p>'; $seenText=true; }
      // signature header row (P33): two-column layout — LEFT=ຕາງໜ່າ, RIGHT=ລາຍເຊັນຂອງຜູ້ກ່ຽວ
      else if($hp['rt']==='signrow'){
        echo '<div class="pv-signrow">';
        echo '<div class="pv-signcol-left"><div class="title">'.nl2br(htmlspecialchars($hp['left'])).'</div>';
        // consume the next paragraph (P34 ປະທານ) as the name line directly under the title
        if(($htmlParas[$i+1]['rt'] ?? '')==='sign'){
          echo '<div class="name">'.nl2br(htmlspecialchars($htmlParas[$i+1]['text'])).'</div>';
          $i++;
        }
        echo '</div>';
        echo '<div class="pv-signcol-right">'.nl2br(htmlspecialchars($hp['right'])).'</div>';
        echo '</div>';
        $seenText=true;
      }
      // date line (P32): right-aligned
      else if($hp['rt']==='date'){ echo '<p class="pv-date">'.htmlspecialchars($hp['text']).'</p>'; $seenText=true; }
      // orphaned ປະທານ (should be consumed by signrow, but fallback)
      else if($hp['rt']==='sign'){ echo '<p class="pv-sign">'.nl2br(htmlspecialchars($hp['text'])).'</p>'; $seenText=true; }
      else if(trim($hp['text'])===''){
        $nextRt = $htmlParas[$i+1]['rt'] ?? '';
        // skip blank between emblem and first text (P01-P02) and before title (P05)
        if($seenText && $nextRt!=='title'){ echo '<div style="height:16px">&nbsp;</div>'; }
      }
      else{ echo '<p style="'.$hp['style'].'">'.nl2br(htmlspecialchars($hp['text'])).'</p>'; $seenText=true; }
    }
    ?>
      </div><!-- .preview-paper -->
    </div>
    <?php require '../components/layout_footer.php'; ?>
    <?php exit; ?>
    <?php
}

// ── item 28 (loan contract ສັນຍາກູ້ຢືມເງິນ): data-summary preview ──
if ((int)$row['doc_type_id'] === 6 && (int)($row['doc_item_id'] ?? 0) === 28) {
    $borrowerName = '';
    if (!empty($row['buyer_id'])) {
        $bs = $conn->prepare("SELECT title, first_name, last_name FROM edoc_citizen WHERE id=?");
        $bs->execute([$row['buyer_id']]);
        $bc = $bs->fetch(PDO::FETCH_ASSOC);
        if ($bc) $borrowerName = trim(($bc['title'] ?? '') . ' ' . ($bc['first_name'] ?? '') . ' ' . ($bc['last_name'] ?? ''));
    }
    $eav = [];
    $ev = $conn->prepare("SELECT f.field_label, fd.field_value
                          FROM edoc_form_data fd
                          JOIN edoc_doc_field f ON f.id = fd.field_id
                          WHERE fd.doc_id = ? ORDER BY f.sort_order");
    $ev->execute([$doc_id]);
    foreach ($ev->fetchAll(PDO::FETCH_ASSOC) as $r) { if ($r['field_value'] !== '' && $r['field_value'] !== null) { $eav[] = $r; } }
    require '../components/layout.php';
    ?>
    <style>
    .ls-card { max-width:720px; margin:0 auto; background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.07); padding:22px 26px; }
    .ls-card h6 { border-bottom:2px solid #044482; padding-bottom:8px; margin-bottom:14px; }
    .ls-row { display:flex; justify-content:space-between; gap:16px; padding:6px 0; border-bottom:1px solid #eef2f7; font-size:.92rem; }
    .ls-row .k { color:#64748b; flex:0 0 45%; }
    .ls-row .v { font-weight:600; text-align:right; }
    .ls-empty { color:#94a3b8; font-style:italic; }
    </style>
    <div class="container-fluid py-3">
      <div class="ls-card">
        <h6><i class="fas fa-hand-holding-usd text-primary"></i> ສັນຍາກູ້ຢືມເງິນ — ສະຫຼຸບຂໍ້ມູນ (Loan contract — data summary)</h6>
        <div class="ls-row"><span class="k">ເລກທີ່ຄຳຮ້ອງ / Doc no</span><span class="v"><?= htmlspecialchars($row['doc_no']) ?></span></div>
        <div class="ls-row"><span class="k">ຝ່າຍ ກໍ ຜູ້ໃຫ້ກູ້ (Lender)</span><span class="v"><?= htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></span></div>
        <div class="ls-row"><span class="k">ຝ່າຍ ຂໍ ຜູ້ຢືມ (Borrower)</span><span class="v"><?= htmlspecialchars($borrowerName) ?></span></div>
        <?php if (!$eav): ?>
          <div class="ls-empty mt-3">ຍັງບໍ່ມີຂໍ້ມູນສັນຍາ (No loan terms captured yet)</div>
        <?php else: foreach ($eav as $f): ?>
          <div class="ls-row"><span class="k"><?= htmlspecialchars($f['field_label']) ?></span><span class="v"><?= htmlspecialchars($f['field_value']) ?></span></div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php require '../components/layout_footer.php'; ?>
    <?php exit; ?>
    <?php
}

// ── item 31 (land sale contract ສັນຍາຊື້-ຂາຍ ກຳມະສິດທີ່ດິນ): data-summary preview ──
if ((int)$row['doc_type_id'] === 6 && (int)($row['doc_item_id'] ?? 0) === 53) {
    $buyerName = '';
    if (!empty($row['buyer_id'])) {
        $bs = $conn->prepare("SELECT title, first_name, last_name FROM edoc_citizen WHERE id=?");
        $bs->execute([$row['buyer_id']]);
        $bc = $bs->fetch(PDO::FETCH_ASSOC);
        if ($bc) $buyerName = trim(($bc['title'] ?? '') . ' ' . ($bc['first_name'] ?? '') . ' ' . ($bc['last_name'] ?? ''));
    }
    $eav = [];
    $ev = $conn->prepare("SELECT f.field_label, fd.field_value
                          FROM edoc_form_data fd
                          JOIN edoc_doc_field f ON f.id = fd.field_id
                          WHERE fd.doc_id = ? ORDER BY f.sort_order");
    $ev->execute([$doc_id]);
    foreach ($ev->fetchAll(PDO::FETCH_ASSOC) as $r) { if ($r['field_value'] !== '' && $r['field_value'] !== null) { $eav[] = $r; } }
    require '../components/layout.php';
    ?>
    <style>
    .ls-card { max-width:720px; margin:0 auto; background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.07); padding:22px 26px; }
    .ls-card h6 { border-bottom:2px solid #044482; padding-bottom:8px; margin-bottom:14px; }
    .ls-row { display:flex; justify-content:space-between; gap:16px; padding:6px 0; border-bottom:1px solid #eef2f7; font-size:.92rem; }
    .ls-row .k { color:#64748b; flex:0 0 45%; }
    .ls-row .v { font-weight:600; text-align:right; }
    .ls-empty { color:#94a3b8; font-style:italic; }
    </style>
    <div class="container-fluid py-3">
      <div class="ls-card">
        <h6><i class="fas fa-handshake text-primary"></i> ສັນຍາຊື້-ຂາຍ ກຳມະສິດທີ່ດິນ — ສະຫຼຸບຂໍ້ມູນ (Land sale contract — data summary)</h6>
        <div class="ls-row"><span class="k">ເລກທີ່ຄຳຮ້ອງ / Doc no</span><span class="v"><?= htmlspecialchars($row['doc_no']) ?></span></div>
        <div class="ls-row"><span class="k">ຜູ້ຂາຍ (Seller)</span><span class="v"><?= htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></span></div>
        <div class="ls-row"><span class="k">ຜູ້ຊື້ (Buyer)</span><span class="v"><?= htmlspecialchars($buyerName) ?></span></div>
        <?php if (!$eav): ?>
          <div class="ls-empty mt-3">ຍັງບໍ່ມີຂໍ້ມູນສັນຍາ (No contract terms captured yet)</div>
        <?php else: foreach ($eav as $f): ?>
          <div class="ls-row"><span class="k"><?= htmlspecialchars($f['field_label']) ?></span><span class="v"><?= htmlspecialchars($f['field_value']) ?></span></div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php require '../components/layout_footer.php'; ?>
    <?php exit; ?>
    <?php
}

// ── item 8 (biography ຊີວະປະຫວັດທົ່ວໄປ, doc_type 1) ──
// template_variant='short' → render 01-08-ປະຫວັດຫຍໍ້.docx as HTML (like kinship).
// otherwise (full/multi-page form) → keep the data-summary card (tables not built yet).
if ((int)$row['doc_type_id'] === 1 && (int)($row['doc_item_id'] ?? 0) === 8) {
    $bio = [];
    $ev = $conn->prepare("SELECT f.field_name, fd.field_value
                          FROM edoc_form_data fd
                          JOIN edoc_doc_field f ON f.id = fd.field_id
                          WHERE fd.doc_id = ?");
    $ev->execute([$doc_id]);
    foreach ($ev->fetchAll(PDO::FETCH_ASSOC) as $r) { if ($r['field_value'] !== '' && $r['field_value'] !== null) { $bio[$r['field_name']] = $r['field_value']; } }
    $bioGet = function($k) use ($bio) { return trim((string)($bio[$k] ?? '')); };
    if (($row['template_variant'] ?? '') === 'short') {
        // ══ SHORT biography (one-page) — template render ══
        $template='/var/www/html/edoc/Doc_files/01-08-ປະຫວັດຫຍໍ້.docx';
        if (!file_exists($template)) { die('Template not found'); }
        $xml = (function() use ($template){ $z=new ZipArchive(); $z->open($template); $s=$z->getFromName('word/document.xml'); $z->close(); return $s; })();
        $dom=new DOMDocument(); $dom->loadXML($xml);
        $ns='http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        require '../components/layout.php';
        ?><style>
        .preview-paper { background:#fff; border:1px solid #ddd; border-radius:8px; box-shadow:0 4px 18px rgba(0,0,0,.09); max-width:794px; margin:0 auto 20px; padding:44px 52px; font-family:'Noto Sans Lao','Phetsarath OT',serif; font-size:15px; line-height:1.9; }
        .preview-paper p { margin:0 0 4px; }
        .pv-title { text-align:center; font-weight:bold; font-size:16px; margin:16px 0 20px !important; }
        .pv-emblem { text-align:center; margin:0 0 6px; }
        .pv-emblem img { width:1.5cm; height:auto; }
        .pv-signrow { display:flex; justify-content:space-between; align-items:flex-start; margin:0; font-size:14px; }
        .pv-signcol-left { padding-left:36pt; }
        .pv-signcol-left .title { text-align:left; margin:0; }
        .pv-signcol-left .name { text-align:center; margin:0; }
        .pv-signcol-right { text-align:right; padding-right:36pt; }
        .pv-note { background:#fff8e1; border:1px solid #eed99a; color:#8a6d00; border-radius:8px; padding:10px 16px; max-width:820px; margin:0 auto 14px; font-size:.9rem; }
        .pv-actions { max-width:820px; margin:0 auto 16px; }
        @media print {
          @page { size: A4; margin: 15mm 17mm; }
          body { background:#fff !important; }
          .preview-paper { max-width:100%; border:none; box-shadow:none; border-radius:0; padding:0; margin:0; }
          .pv-note, .pv-actions, .breadcrumb { display:none !important; }
          .preview-paper p { break-inside: avoid; }
        }
        </style>
        <div class="content-wrapper p-3">
          <nav class="breadcrumb mb-2">
            <ol class="breadcrumb mb-2">
              <li class="breadcrumb-item"><a href="list_edoc_document.php">ບໍລິການເອກະສານ</a></li>
              <li class="breadcrumb-item"><a href="review_edoc_document.php?doc_id=<?= $doc_id ?>">ຈັດການ</a></li>
              <li class="breadcrumb-item active">ສະແດງຕົວຢ່າງ (Preview)</li>
            </ol>
          </nav>
          <div class="pv-actions text-center">
            <a class="btn btn-outline-secondary" href="review_edoc_document.php?doc_id=<?= $doc_id ?>"><i class="fas fa-arrow-left"></i> ກັບຄືນ (Back to review)</a>
            <button class="btn btn-outline-primary ms-2" onclick="window.print()"><i class="fas fa-print"></i> ພິມ (Print)</button>
            <a class="btn btn-outline-success ms-2" target="_blank" href="../generate/generate_edoc_document.php?doc_id=<?= $doc_id ?>"><i class="fas fa-file-word"></i> ດາວໂຫລດ Word (Download)</a>
          </div>
          <div class="pv-note"><i class="fas fa-info-circle"></i> ຕົວຢ່າງສະແດງຕາມແບບຟອມທາງການ — ແບບຟອມສັ້ນ (Short form preview)</div>
          <div class="preview-paper">
        <?php
        $title=($row['gender']==='M')?'ທ້າວ':(($row['gender']==='F')?'ນາງ':''); $today=date('d/m/Y');
        $bioName = trim($title . ' ' . $row['first_name'] . ' ' . $row['last_name']);
        $originName=resolveName($conn,'edoc_country',$row['origin']); $nationalityName=resolveName($conn,'edoc_country',$row['nationality']);
        $ethnicityName=resolveName($conn,'edoc_ethnicity',$row['ethnicity']); $religionName=resolveName($conn,'edoc_religion',$row['religion']);
        $occupationName=resolveName($conn,'edoc_occupation',$row['occupation']);
        $birthVillageName=resolveName($conn,'edoc_village',$row['birth_village']);
        $birthDistrictName=resolveName($conn,'edoc_district',$row['birth_district']);
        $birthProvinceName=resolveName($conn,'edoc_province',$row['birth_province']);
        $ageOf = function($dob) { return $dob ? floor((time() - strtotime($dob)) / 31536000) : ''; };
        $paras=$dom->getElementsByTagNameNS($ns,'p');
        $occurrence=[];
        foreach($paras as $p){
          $full=''; foreach(wRuns($p) as $r)$full.=runText($r);
          $trimmed=ltrim($full);
          // 1) self name line (P6) + father (P14) + mother (P15) — all start ຊື່ ແລະ ນາມສະກຸນ
          if(strpos($trimmed,'ຊື່ ແລະ ນາມສະກຸນ')===0){
            $i=$occurrence['name']??0; $occurrence['name']=$i+1;
            if($i===0){ // self
              fillDots($p,'ຊື່ ແລະ ນາມສະກຸນ:',$bioName);
              fillDots($p,'ວັນ,ເດືອນ,ປີເກີດ',fmtDate($row['dob']));
              fillDots($p,'ບ້ານເກີດ',$birthVillageName);
              fillDots($p,'ເມືອງ',$birthDistrictName);
              fillDots($p,'ແຂວງ',$birthProvinceName);
              fillDots($p,'ເຊື້ອຊາດ',$originName);
              fillDots($p,'ສັນຊາດ',$nationalityName);
            } else if($i===1){ // father
              fillDots($p,'ຊື່ ແລະ ນາມສະກຸນພໍ່ :',$bioGet('bio_father_name'));
              fillDots($p,'ອາຍຸ',$ageOf($bioGet('bio_father_dob')));
              fillDots($p,'ສັນຊາດ',$bioGet('bio_father_nat'));
              fillDots($p,'ເຊື້ອຊາດ',$bioGet('bio_father_eth'));
              fillDots($p,'ອາຊີບ',$bioGet('bio_father_job'));
              fillDots($p,'ປະຈຸບັນຢູ່ບ້ານ',$bioGet('bio_father_village'));
              fillDots($p,'ເມືອງ',$bioGet('bio_father_district'));
              fillDots($p,'ແຂວງ',$bioGet('bio_father_province'));
            } else { // mother
              fillDots($p,'ຊື່ ແລະ ນາມສະກຸນແມ່: :',$bioGet('bio_mother_name'));
              fillDots($p,'ອາຍຸ',$ageOf($bioGet('bio_mother_dob')));
              fillDots($p,'ສັນຊາດ',$bioGet('bio_mother_nat'));
              fillDots($p,'ເຊື້ອຊາດ',$bioGet('bio_mother_eth'));
              fillDots($p,'ອາຊີບ',$bioGet('bio_mother_job'));
              fillDots($p,'ປະຈຸບັນຢູ່ບ້ານ',$bioGet('bio_mother_village'));
              fillDots($p,'ເມືອງ',$bioGet('bio_mother_district'));
              fillDots($p,'ແຂວງ',$bioGet('bio_mother_province'));
            }
          }
          // 2) self second paragraph (P7): ethnicity ... province
          else if(strpos($trimmed,'ຊົນເຜົ່າ')===0){
            fillDots($p,'ຊົນເຜົ່າ',$ethnicityName);
            fillDots($p,'ສາສະໜາ',$religionName);
            fillDots($p,'ສຳມະໂນຄົວເລກທີ',$bioGet('bio_family_book_no'));
            fillDots($p,'ລົງວັນທີ',fmtDate($bioGet('bio_family_book_date')));
            fillDots($p,'ບັດປະຈຳຕົວເລກທີ',$row['id_card']);
            fillDots($p,'ລະດັບການສຶກສາ',$bioGet('bio_educ_upper'));
            fillDots($p,'ລະດັບວິຊາສະເພາະ',$bioGet('bio_special'));
            fillDots($p,'ອາຊີບປະຈຸບັນ',$occupationName);
            fillDots($p,'ບ່ອນປະຈຳການ',$bioGet('bio_workplace'));
            fillDots($p,'ໜ້າທີ່ຮັບຜິດຊອບ',$bioGet('bio_position'));
            fillDots($p,'ວັນ/ເດືອນ/ປີສັງກັດລັດ',fmtDate($bioGet('bio_party_date')));
            fillDots($p,'ປະຈຸບັນຢູ່ບ້ານ',$row['village_name']);
            fillDots($p,'ເມືອງ',$row['district_name'] ?? '');
            fillDots($p,'ແຂວງ',$row['province_name'] ?? '');
          }
          // 3) signature place + date (P24)
          else if(strpos($trimmed,'ທີ່')===0 && strpos($full,'ວັນທີ')!==false){
            fillDots($p,'ທີ່',$row['province_name'] ?? '');
            fillDots($p,'ວັນທີ',$today);
          }
        }
        // ── render as HTML (mirrors kinship: emblem / title / signrow / date / numbered paragraphs) ──
        $emblemB64='';
        $ze=new ZipArchive(); if($ze->open($template)===true){
          $img=$ze->getFromName('word/media/image1.png'); if($img===false)$img=$ze->getFromName('word/media/image1.jpeg'); if($img===false)$img=$ze->getFromName('word/media/image1.jpg');
          $ze->close(); if($img!==false)$emblemB64=base64_encode($img);
        }
        $xpath=new DOMXPath($dom); $xpath->registerNamespace('w',$ns);
        $htmlParas=[]; $imgFound=false;
        $numCnt=[];
        foreach($paras as $p){
          $text=''; foreach($xpath->query('.//w:t',$p) as $t)$text.=$t->textContent;
          $jc=$xpath->query('.//w:jc',$p)->item(0); $align=$jc?$jc->getAttribute('w:val'):'both';
          $hasEmblem= (!$imgFound) && ($xpath->query('.//*[local-name()="drawing"]',$p)->length>0 || $xpath->query('.//*[local-name()="pict"]',$p)->length>0);
          if($hasEmblem)$imgFound=true;
          $numPrNode=$xpath->query('.//w:numPr',$p)->item(0);
          $numId=0; if($numPrNode){ $nn=$xpath->query('.//w:numId',$numPrNode)->item(0); $numId=$nn?(int)$nn->getAttribute('w:val'):0; }
          $numPrefix='';
          if($numId>0){ $numCnt[$numId]=($numCnt[$numId]??0)+1; $numPrefix=$numCnt[$numId].'. '; }
          // dots are fill placeholders → ", "; literal section numbers keep single dot
          $t=preg_replace('/\s*\.{4,}\s*/u', ', ', $text);
          $t=preg_replace('/\s{2,}/',' ',$t); $t=preg_replace('/,\s*,/',',',$t); $t=preg_replace('/\s+,/',',',$t); $t=preg_replace('/,\s+$/','',$t); $t=preg_replace('/^\s+/','',$t);
          $rt='normal';
          if($hasEmblem){ $rt='emblem'; }
          else if($t==='ຊີວະປະຫວັດຫຍໍ້'){ $rt='title'; }
          else if(strpos($t,'ລາຍເຊັນ')!==false){ $rt='signrow'; }
          else if($t==='ປະທານ'){ $rt='sign'; }
          if($rt==='normal' && $numPrefix!=='') $t=$numPrefix.$t;
          $sigLeft=''; $sigRight='';
          if($rt==='signrow'){
            $parts=preg_split('/\s{4,}/u', $text, 2);
            $sigLeft=trim(preg_replace('/\s+/u',' ', $parts[0]??''));
            $sigRight=trim(preg_replace('/\s+/u',' ', $parts[1]??''));
            $sigLeft=preg_replace('/\s*\.{4,}\s*/u',', ',$sigLeft);
            $sigRight=preg_replace('/\s*\.{4,}\s*/u',', ',$sigRight);
          }
          $style='';
          $in=$xpath->query('.//w:ind',$p)->item(0);
          if($in){
            $fl=(int)$in->getAttribute('w:firstLine'); $lf=(int)$in->getAttribute('w:left'); $hg=(int)$in->getAttribute('w:hanging');
            if($lf){ $style.='padding-left:'.($lf/20).'pt;'; if($hg)$style.='text-indent:-'.($hg/20).'pt;'; }
            if($fl && !$hg) $style.='text-indent:'.(min($fl,567)/20).'pt;';
          }
          if($align==='center')$style.='text-align:center;';
          else if($align==='right')$style.='text-align:right;';
          $htmlParas[]=['text'=>$t,'rt'=>$rt,'style'=>$style,'left'=>$sigLeft,'right'=>$sigRight];
        }
        $seenText=false;
        for($i=0;$i<count($htmlParas);$i++){
          $hp=$htmlParas[$i];
          if($hp['rt']==='skip')continue;
          if($hp['rt']==='emblem'&&$emblemB64!==''){ echo '<div class="pv-emblem"><img src="data:image/png;base64,'.$emblemB64.'" alt="ກາແຫ່ງຊາດ"></div>'; }
          else if($hp['rt']==='title'){ echo '<p class="pv-title">'.htmlspecialchars($hp['text']).'</p>'; $seenText=true; }
          else if($hp['rt']==='signrow'){
            echo '<div class="pv-signrow">';
            echo '<div class="pv-signcol-left"><div class="title">'.nl2br(htmlspecialchars($hp['left'])).'</div>';
            if(($htmlParas[$i+1]['rt'] ?? '')==='sign'){
              echo '<div class="name">'.nl2br(htmlspecialchars($htmlParas[$i+1]['text'])).'</div>';
              $i++;
            }
            echo '</div>';
            echo '<div class="pv-signcol-right">'.nl2br(htmlspecialchars($hp['right'])).'</div>';
            echo '</div>';
            $seenText=true;
          }
          else if($hp['rt']==='sign'){ echo '<p class="pv-sign">'.nl2br(htmlspecialchars($hp['text'])).'</p>'; $seenText=true; }
          else if(trim($hp['text'])===''){
            $nextRt = $htmlParas[$i+1]['rt'] ?? '';
            if($seenText && $nextRt!=='title'){ echo '<div style="height:16px">&nbsp;</div>'; }
          }
          else{ echo '<p style="'.$hp['style'].'">'.nl2br(htmlspecialchars($hp['text'])).'</p>'; $seenText=true; }
        }
        ?>
          </div><!-- .preview-paper -->
        </div>
        <?php require '../components/layout_footer.php'; ?>
        <?php exit; ?>
        <?php
    } else {
        // ══ FULL / multi-page form — data-summary card (tables not built yet) ══
        $applicant = trim(($row['title'] ?? '') . ' ' . ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        ?>
    <div class="container-fluid"><div class="card"><div class="card-body">
        <h5 class="mb-3">ຊີວະປະຫວັດທົ່ວໄປ (Biography) — ແບບຟອມເຕັມ (Full form)</h5>
        <div class="ls-row"><span class="k">ຜູ້ຍື່ນ (Applicant)</span><span class="v"><?= htmlspecialchars($applicant) ?></span></div>
        <div class="ls-row"><span class="k">ພໍ່ (Father)</span><span class="v"><?= htmlspecialchars($bioGet('bio_father_name')) ?></span></div>
        <div class="ls-row"><span class="k">ແມ່ (Mother)</span><span class="v"><?= htmlspecialchars($bioGet('bio_mother_name')) ?></span></div>
        <div class="ls-row"><span class="k">ຜົວ/ເມຍ (Spouse)</span><span class="v"><?= htmlspecialchars($bioGet('bio_spouse_name')) ?></span></div>
        <div class="ls-row"><span class="k">ອາຊີບ (Workplace)</span><span class="v"><?= htmlspecialchars($bioGet('bio_workplace')) ?></span></div>
        <div class="ls-row"><span class="k">ໜ້າທີ່ (Position)</span><span class="v"><?= htmlspecialchars($bioGet('bio_position')) ?></span></div>
        <div class="ls-row"><span class="k">ລູກ (Children)</span><span class="v"><?= htmlspecialchars($bioGet('bio_children_count')) ?></span></div>
        <div class="ls-row"><span class="k">ສຸຂະພາບ (Health)</span><span class="v"><?= htmlspecialchars($bioGet('bio_health')) ?></span></div>
    </div></div></div>
    <?php require '../components/layout_footer.php'; ?>
    <?php exit; ?>
    <?php
    }
}

// ── item 30 (lease contract ໃບສັນຍາເຊົ່າທີ່ດິນ ແລະ ເຮືອນ, doc_type 6): data-summary preview ──
if ((int)$row['doc_type_id'] === 6 && (int)($row['doc_item_id'] ?? 0) === 30) {
    $renterName = '';
    if (!empty($row['buyer_id'])) {
        $bs = $conn->prepare("SELECT title, first_name, last_name FROM edoc_citizen WHERE id=?");
        $bs->execute([$row['buyer_id']]);
        $bc = $bs->fetch(PDO::FETCH_ASSOC);
        if ($bc) $renterName = trim(($bc['title'] ?? '') . ' ' . ($bc['first_name'] ?? '') . ' ' . ($bc['last_name'] ?? ''));
    }
    $eav = [];
    $ev = $conn->prepare("SELECT f.field_label, fd.field_value
                          FROM edoc_form_data fd
                          JOIN edoc_doc_field f ON f.id = fd.field_id
                          WHERE fd.doc_id = ? ORDER BY f.sort_order");
    $ev->execute([$doc_id]);
    foreach ($ev->fetchAll(PDO::FETCH_ASSOC) as $r) { if ($r['field_value'] !== '' && $r['field_value'] !== null) { $eav[] = $r; } }
    require '../components/layout.php';
    ?>
    <style>
    .ls-card { max-width:720px; margin:0 auto; background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.07); padding:22px 26px; }
    .ls-card h6 { border-bottom:2px solid #044482; padding-bottom:8px; margin-bottom:14px; }
    .ls-row { display:flex; justify-content:space-between; gap:16px; padding:6px 0; border-bottom:1px solid #eef2f7; font-size:.92rem; }
    .ls-row .k { color:#64748b; flex:0 0 45%; }
    .ls-row .v { font-weight:600; text-align:right; }
    .ls-empty { color:#94a3b8; font-style:italic; }
    </style>
    <div class="container-fluid py-3">
      <div class="ls-card">
        <h6><i class="fas fa-file-contract text-primary"></i> ໃບສັນຍາເຊົ່າທີ່ດິນ ແລະ ເຮືອນ — ສະຫຼຸບຂໍ້ມູນ (Lease contract — data summary)</h6>
        <div class="ls-row"><span class="k">ເລກທີ່ຄຳຮ້ອງ / Doc no</span><span class="v"><?= htmlspecialchars($row['doc_no']) ?></span></div>
        <div class="ls-row"><span class="k">ເຈົ້າຂອງຊັບ (Owner)</span><span class="v"><?= htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></span></div>
        <div class="ls-row"><span class="k">ຜູ້ເຂົ້າເຊົ່າ (Renter)</span><span class="v"><?= htmlspecialchars($renterName) ?></span></div>
        <?php if (!$eav): ?>
          <div class="ls-empty mt-3">ຍັງບໍ່ມີຂໍ້ມູນສັນຍາ (No contract terms captured yet)</div>
        <?php else: foreach ($eav as $f): ?>
          <div class="ls-row"><span class="k"><?= htmlspecialchars($f['field_label']) ?></span><span class="v"><?= htmlspecialchars($f['field_value']) ?></span></div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php require '../components/layout_footer.php'; ?>
    <?php exit; ?>
    <?php
}

// ── item 54 (relocation certificate ໃບຍົກຍ້າຍ, doc_type 1): data-summary preview ──
if ((int)$row['doc_type_id'] === 1 && (int)($row['doc_item_id'] ?? 0) === 54) {
    $eav = [];
    $ev = $conn->prepare("SELECT f.field_label, fd.field_value
                          FROM edoc_form_data fd
                          JOIN edoc_doc_field f ON f.id = fd.field_id
                          WHERE fd.doc_id = ? ORDER BY f.sort_order");
    $ev->execute([$doc_id]);
    foreach ($ev->fetchAll(PDO::FETCH_ASSOC) as $r) { if ($r['field_value'] !== '' && $r['field_value'] !== null) { $eav[] = $r; } }
    $applicant = trim(($row['title'] ?? '') . ' ' . ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    require '../components/layout.php';
    ?>
    <style>
    .ls-card { max-width:720px; margin:0 auto; background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.07); padding:22px 26px; }
    .ls-card h6 { border-bottom:2px solid #044482; padding-bottom:8px; margin-bottom:14px; }
    .ls-row { display:flex; justify-content:space-between; gap:16px; padding:6px 0; border-bottom:1px solid #eef2f7; font-size:.92rem; }
    .ls-row .k { color:#64748b; flex:0 0 45%; }
    .ls-row .v { font-weight:600; text-align:right; }
    .ls-empty { color:#94a3b8; font-style:italic; }
    </style>
    <div class="container-fluid py-3">
      <div class="ls-card">
        <h6><i class="fas fa-file-signature text-primary"></i> ໃບຍົກຍ້າຍ — ສະຫຼຸບຂໍ້ມູນ (Relocation certificate — data summary)</h6>
        <div class="ls-row"><span class="k">ເລກທີ່ຄຳຮ້ອງ / Doc no</span><span class="v"><?= htmlspecialchars($row['doc_no']) ?></span></div>
        <div class="ls-row"><span class="k">ຜູ້ຖືກຍົກຍ້າຍ (Person being relocated)</span><span class="v"><?= htmlspecialchars($applicant) ?></span></div>
        <?php if (!$eav): ?>
          <div class="ls-empty mt-3">ຍັງບໍ່ມີຂໍ້ມູນ (No data captured yet)</div>
        <?php else: foreach ($eav as $f): ?>
          <div class="ls-row"><span class="k"><?= htmlspecialchars($f['field_label']) ?></span><span class="v"><?= htmlspecialchars($f['field_value']) ?></span></div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php require '../components/layout_footer.php'; ?>
    <?php exit; ?>
    <?php
}

// ── item 55 (damage certificate ໃບຢັ້ງຢືນການເສຍຫາຍ, doc_type 1): data-summary preview ──
if ((int)$row['doc_type_id'] === 1 && (int)($row['doc_item_id'] ?? 0) === 55) {
    $eav = [];
    $ev = $conn->prepare("SELECT f.field_label, fd.field_value
                          FROM edoc_form_data fd
                          JOIN edoc_doc_field f ON f.id = fd.field_id
                          WHERE fd.doc_id = ? ORDER BY f.sort_order");
    $ev->execute([$doc_id]);
    foreach ($ev->fetchAll(PDO::FETCH_ASSOC) as $r) { if ($r['field_value'] !== '' && $r['field_value'] !== null) { $eav[] = $r; } }
    $applicant = trim(($row['title'] ?? '') . ' ' . ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    require '../components/layout.php';
    ?>
    <style>
    .ls-card { max-width:720px; margin:0 auto; background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.07); padding:22px 26px; }
    .ls-card h6 { border-bottom:2px solid #044482; padding-bottom:8px; margin-bottom:14px; }
    .ls-row { display:flex; justify-content:space-between; gap:16px; padding:6px 0; border-bottom:1px solid #eef2f7; font-size:.92rem; }
    .ls-row .k { color:#64748b; flex:0 0 45%; }
    .ls-row .v { font-weight:600; text-align:right; }
    .ls-empty { color:#94a3b8; font-style:italic; }
    </style>
    <div class="container-fluid py-3">
      <div class="ls-card">
        <h6><i class="fas fa-exclamation-triangle text-warning"></i> ໃບຢັ້ງຢືນການເສຍຫາຍ — ສະຫຼຸບຂໍ້ມູນ (Damage/Loss certificate — data summary)</h6>
        <div class="ls-row"><span class="k">ເລກທີ່ຄຳຮ້ອງ / Doc no</span><span class="v"><?= htmlspecialchars($row['doc_no']) ?></span></div>
        <div class="ls-row"><span class="k">ຜູ້ແຈ້ງ (claimant)</span><span class="v"><?= htmlspecialchars($applicant) ?></span></div>
        <?php if (!$eav): ?>
          <div class="ls-empty mt-3">ຍັງບໍ່ມີຂໍ້ມູນ (No data captured yet)</div>
        <?php else: foreach ($eav as $f): ?>
          <div class="ls-row"><span class="k"><?= htmlspecialchars($f['field_label']) ?></span><span class="v"><?= htmlspecialchars($f['field_value']) ?></span></div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php require '../components/layout_footer.php'; ?>
    <?php exit; ?>
    <?php
}

// ── item 20 (marriage request ໃບຄຳຮ້ອງ ຂໍຈົດທະບຽນແຕ່ງດອງ, doc_type 2): data-summary preview ──
if ((int)$row['doc_type_id'] === 2 && (int)($row['doc_item_id'] ?? 0) === 20) {
    $brideName = '';
    if (!empty($row['buyer_id'])) {
        $bs = $conn->prepare("SELECT title, first_name, last_name FROM edoc_citizen WHERE id=?");
        $bs->execute([$row['buyer_id']]);
        $bc = $bs->fetch(PDO::FETCH_ASSOC);
        if ($bc) $brideName = trim(($bc['title'] ?? '') . ' ' . ($bc['first_name'] ?? '') . ' ' . ($bc['last_name'] ?? ''));
    }
    $eav = [];
    $ev = $conn->prepare("SELECT f.field_label, fd.field_value
                          FROM edoc_form_data fd
                          JOIN edoc_doc_field f ON f.id = fd.field_id
                          WHERE fd.doc_id = ? ORDER BY f.sort_order");
    $ev->execute([$doc_id]);
    foreach ($ev->fetchAll(PDO::FETCH_ASSOC) as $r) { if ($r['field_value'] !== '' && $r['field_value'] !== null) { $eav[] = $r; } }
    require '../components/layout.php';
    ?>
    <style>
    .ls-card { max-width:720px; margin:0 auto; background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.07); padding:22px 26px; }
    .ls-card h6 { border-bottom:2px solid #044482; padding-bottom:8px; margin-bottom:14px; }
    .ls-row { display:flex; justify-content:space-between; gap:16px; padding:6px 0; border-bottom:1px solid #eef2f7; font-size:.92rem; }
    .ls-row .k { color:#64748b; flex:0 0 45%; }
    .ls-row .v { font-weight:600; text-align:right; }
    .ls-empty { color:#94a3b8; font-style:italic; }
    </style>
    <div class="container-fluid py-3">
      <div class="ls-card">
        <h6><i class="fas fa-ring text-danger"></i> ໃບຄຳຮ້ອງ ຂໍຈົດທະບຽນແຕ່ງດອງ — ສະຫຼຸບຂໍ້ມູນ (Marriage registration request — data summary)</h6>
        <div class="ls-row"><span class="k">ເລກທີ່ຄຳຮ້ອງ / Doc no</span><span class="v"><?= htmlspecialchars($row['doc_no']) ?></span></div>
        <div class="ls-row"><span class="k">ຜູ້ຊາຍ (Groom)</span><span class="v"><?= htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></span></div>
        <div class="ls-row"><span class="k">ຜູ້ຍິງ (Bride)</span><span class="v"><?= htmlspecialchars($brideName) ?></span></div>
        <?php if (!$eav): ?>
          <div class="ls-empty mt-3">ຍັງບໍ່ມີຂໍ້ມູນຄຳຮ້ອງ (No request details captured yet)</div>
        <?php else: foreach ($eav as $f): ?>
          <div class="ls-row"><span class="k"><?= htmlspecialchars($f['field_label']) ?></span><span class="v"><?= htmlspecialchars($f['field_value']) ?></span></div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php require '../components/layout_footer.php'; ?>
    <?php exit; ?>
    <?php
}

// ── item 56 (single status certificate ໃບຢັ້ງຢືນຄວາມເປັນໂສດ, doc_type 1): data-summary preview ──
if ((int)$row['doc_type_id'] === 1 && (int)($row['doc_item_id'] ?? 0) === 56) {
    $eav = [];
    $ev = $conn->prepare("SELECT f.field_label, fd.field_value
                          FROM edoc_form_data fd
                          JOIN edoc_doc_field f ON f.id = fd.field_id
                          WHERE fd.doc_id = ? ORDER BY f.sort_order");
    $ev->execute([$doc_id]);
    foreach ($ev->fetchAll(PDO::FETCH_ASSOC) as $r) { if ($r['field_value'] !== '' && $r['field_value'] !== null) { $eav[] = $r; } }
    $applicant = trim(($row['title'] ?? '') . ' ' . ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    require '../components/layout.php';
    ?>
    <style>
    .ls-card { max-width:720px; margin:0 auto; background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.07); padding:22px 26px; }
    .ls-card h6 { border-bottom:2px solid #044482; padding-bottom:8px; margin-bottom:14px; }
    .ls-row { display:flex; justify-content:space-between; gap:16px; padding:6px 0; border-bottom:1px solid #eef2f7; font-size:.92rem; }
    .ls-row .k { color:#64748b; flex:0 0 45%; }
    .ls-row .v { font-weight:600; text-align:right; }
    .ls-empty { color:#94a3b8; font-style:italic; }
    </style>
    <div class="container-fluid py-3">
      <div class="ls-card">
        <h6><i class="fas fa-user text-info"></i> ໃບຢັ້ງຢືນຄວາມເປັນໂສດ — ສະຫຼຸບຂໍ້ມູນ (Single Status certificate — data summary)</h6>
        <div class="ls-row"><span class="k">ເລກທີ່ຄຳຮ້ອງ / Doc no</span><span class="v"><?= htmlspecialchars($row['doc_no']) ?></span></div>
        <div class="ls-row"><span class="k">ຜູ້ກ່ຽວ (Subject)</span><span class="v"><?= htmlspecialchars($applicant) ?></span></div>
        <?php if (!$eav): ?>
          <div class="ls-empty mt-3">ຍັງບໍ່ມີຂໍ້ມູນ (No data captured yet)</div>
        <?php else: foreach ($eav as $f): ?>
          <div class="ls-row"><span class="k"><?= htmlspecialchars($f['field_label']) ?></span><span class="v"><?= htmlspecialchars($f['field_value']) ?></span></div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php require '../components/layout_footer.php'; ?>
    <?php exit; ?>
    <?php
}

// ── item 57 (foster child certificate ໃບຢັ້ງຢືນລູກທາງ (ລູກຊູ້), doc_type 1): data-summary preview ──
if ((int)$row['doc_type_id'] === 1 && (int)($row['doc_item_id'] ?? 0) === 57) {
    $eav = [];
    $ev = $conn->prepare("SELECT f.field_label, fd.field_value
                          FROM edoc_form_data fd
                          JOIN edoc_doc_field f ON f.id = fd.field_id
                          WHERE fd.doc_id = ? ORDER BY f.sort_order");
    $ev->execute([$doc_id]);
    foreach ($ev->fetchAll(PDO::FETCH_ASSOC) as $r) { if ($r['field_value'] !== '' && $r['field_value'] !== null) { $eav[] = $r; } }
    $applicant = trim(($row['title'] ?? '') . ' ' . ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    require '../components/layout.php';
    ?>
    <style>
    .ls-card { max-width:720px; margin:0 auto; background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.07); padding:22px 26px; }
    .ls-card h6 { border-bottom:2px solid #044482; padding-bottom:8px; margin-bottom:14px; }
    .ls-row { display:flex; justify-content:space-between; gap:16px; padding:6px 0; border-bottom:1px solid #eef2f7; font-size:.92rem; }
    .ls-row .k { color:#64748b; flex:0 0 45%; }
    .ls-row .v { font-weight:600; text-align:right; }
    .ls-empty { color:#94a3b8; font-style:italic; }
    </style>
    <div class="container-fluid py-3">
      <div class="ls-card">
        <h6><i class="fas fa-child text-warning"></i> ໃບຢັ້ງຢືນລູກທາງ (ລູກຊູ້) — ສະຫຼຸບຂໍ້ມູນ (Foster child certificate — data summary)</h6>
        <div class="ls-row"><span class="k">ເລກທີ່ຄຳຮ້ອງ / Doc no</span><span class="v"><?= htmlspecialchars($row['doc_no']) ?></span></div>
        <div class="ls-row"><span class="k">ຜູ້ກ່ຽວ (Subject)</span><span class="v"><?= htmlspecialchars($applicant) ?></span></div>
        <?php if (!$eav): ?>
          <div class="ls-empty mt-3">ຍັງບໍ່ມີຂໍ້ມູນ (No data captured yet)</div>
        <?php else: foreach ($eav as $f): ?>
          <div class="ls-row"><span class="k"><?= htmlspecialchars($f['field_label']) ?></span><span class="v"><?= htmlspecialchars($f['field_value']) ?></span></div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php require '../components/layout_footer.php'; ?>
    <?php exit; ?>
    <?php
}

// ── item 4 (damage notification ໃບແຈ້ງເສຍຫາຍ, doc_type 4): data-summary preview ──
if ((int)$row['doc_type_id'] === 4 && (int)($row['doc_item_id'] ?? 0) === 4) {
    $eav = [];
    $ev = $conn->prepare("SELECT f.field_label, fd.field_value
                          FROM edoc_form_data fd
                          JOIN edoc_doc_field f ON f.id = fd.field_id
                          WHERE fd.doc_id = ? ORDER BY f.sort_order");
    $ev->execute([$doc_id]);
    foreach ($ev->fetchAll(PDO::FETCH_ASSOC) as $r) { if ($r['field_value'] !== '' && $r['field_value'] !== null) { $eav[] = $r; } }
    $applicant = trim(($row['title'] ?? '') . ' ' . ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    require '../components/layout.php';
    ?>
    <style>
    .ls-card { max-width:720px; margin:0 auto; background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.07); padding:22px 26px; }
    .ls-card h6 { border-bottom:2px solid #044482; padding-bottom:8px; margin-bottom:14px; }
    .ls-row { display:flex; justify-content:space-between; gap:16px; padding:6px 0; border-bottom:1px solid #eef2f7; font-size:.92rem; }
    .ls-row .k { color:#64748b; flex:0 0 45%; }
    .ls-row .v { font-weight:600; text-align:right; }
    .ls-empty { color:#94a3b8; font-style:italic; }
    </style>
    <div class="container-fluid py-3">
      <div class="ls-card">
        <h6><i class="fas fa-exclamation-circle text-danger"></i> ໃບແຈ້ງເສຍຫາຍ — ສະຫຼຸບຂໍ້ມູນ (Damage/Loss notification — data summary)</h6>
        <div class="ls-row"><span class="k">ເລກທີ່ຄຳຮ້ອງ / Doc no</span><span class="v"><?= htmlspecialchars($row['doc_no']) ?></span></div>
        <div class="ls-row"><span class="k">ຜູ້ແຈ້ງ (Notifier)</span><span class="v"><?= htmlspecialchars($applicant) ?></span></div>
        <?php if (!$eav): ?>
          <div class="ls-empty mt-3">ຍັງບໍ່ມີຂໍ້ມູນ (No data captured yet)</div>
        <?php else: foreach ($eav as $f): ?>
          <div class="ls-row"><span class="k"><?= htmlspecialchars($f['field_label']) ?></span><span class="v"><?= htmlspecialchars($f['field_value']) ?></span></div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php require '../components/layout_footer.php'; ?>
    <?php exit; ?>
    <?php
}

// ── item 58 (divorce record ບົດບັນທຶກການຢ່າຮ້າງ, doc_type 5): data-summary preview ──
if ((int)$row['doc_type_id'] === 5 && (int)($row['doc_item_id'] ?? 0) === 58) {
    $wifeName = '';
    if (!empty($row['buyer_id'])) {
        $bs = $conn->prepare("SELECT title, first_name, last_name FROM edoc_citizen WHERE id=?");
        $bs->execute([$row['buyer_id']]);
        $bc = $bs->fetch(PDO::FETCH_ASSOC);
        if ($bc) $wifeName = trim(($bc['title'] ?? '') . ' ' . ($bc['first_name'] ?? '') . ' ' . ($bc['last_name'] ?? ''));
    }
    $eav = [];
    $ev = $conn->prepare("SELECT f.field_label, fd.field_value
                          FROM edoc_form_data fd
                          JOIN edoc_doc_field f ON f.id = fd.field_id
                          WHERE fd.doc_id = ? ORDER BY f.sort_order");
    $ev->execute([$doc_id]);
    foreach ($ev->fetchAll(PDO::FETCH_ASSOC) as $r) { if ($r['field_value'] !== '' && $r['field_value'] !== null) { $eav[] = $r; } }
    $husbandName = trim(($row['title'] ?? '') . ' ' . ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    require '../components/layout.php';
    ?>
    <style>
    .ls-card { max-width:720px; margin:0 auto; background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.07); padding:22px 26px; }
    .ls-card h6 { border-bottom:2px solid #044482; padding-bottom:8px; margin-bottom:14px; }
    .ls-row { display:flex; justify-content:space-between; gap:16px; padding:6px 0; border-bottom:1px solid #eef2f7; font-size:.92rem; }
    .ls-row .k { color:#64748b; flex:0 0 45%; }
    .ls-row .v { font-weight:600; text-align:right; }
    .ls-empty { color:#94a3b8; font-style:italic; }
    </style>
    <div class="container-fluid py-3">
      <div class="ls-card">
        <h6><i class="fas fa-file-invoice text-secondary"></i> ບົດບັນທຶກການຢ່າຮ້າງ — ສະຫຼຸບຂໍ້ມູນ (Divorce record — data summary)</h6>
        <div class="ls-row"><span class="k">ເລກທີ່ຄຳຮ້ອງ / Doc no</span><span class="v"><?= htmlspecialchars($row['doc_no']) ?></span></div>
        <div class="ls-row"><span class="k">ຜົວ (Husband)</span><span class="v"><?= htmlspecialchars($husbandName) ?></span></div>
        <div class="ls-row"><span class="k">ເມຍ (Wife)</span><span class="v"><?= htmlspecialchars($wifeName) ?></span></div>
        <?php if (!$eav): ?>
          <div class="ls-empty mt-3">ຍັງບໍ່ມີຂໍ້ມູນບັນທຶກ (No record details captured yet)</div>
        <?php else: foreach ($eav as $f): ?>
          <div class="ls-row"><span class="k"><?= htmlspecialchars($f['field_label']) ?></span><span class="v"><?= htmlspecialchars($f['field_value']) ?></span></div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php require '../components/layout_footer.php'; ?>
    <?php exit; ?>
    <?php
}

$res = null;
$rs = $conn->prepare("SELECT * FROM edoc_dc_residence WHERE doc_id = ?");
$rs->execute([$doc_id]);
$res = $rs->fetch(PDO::FETCH_ASSOC) ?: [];
// fallback: if legacy table is empty or missing father/mother, read from EAV
$eavFallback = [];
if (empty($res['father_name']) || empty($res['mother_name'])) {
    $ev = $conn->prepare("SELECT f.field_name, fd.field_value
                          FROM edoc_form_data fd
                          JOIN edoc_doc_field f ON f.id = fd.field_id
                          WHERE fd.doc_id = ? AND f.field_name IN ('father_name','mother_name')");
    $ev->execute([$doc_id]);
    foreach ($ev->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $eavFallback[$r['field_name']] = $r['field_value'];
    }
}
if (empty($res['father_name']) && !empty($eavFallback['father_name'])) $res['father_name'] = $eavFallback['father_name'];
if (empty($res['mother_name']) && !empty($eavFallback['mother_name'])) $res['mother_name'] = $eavFallback['mother_name'];

// ── resolve codes → Lao names ────────────────────────────────────────
function resolveName($conn, $table, $code) {
  if ($code === '' || $code === null) return '';
  $st = $conn->prepare("SELECT name_lo FROM $table WHERE code = ? LIMIT 1");
  $st->execute([$code]);
  $n = $st->fetchColumn();
  return $n !== false ? $n : $code;
}
function geoName($conn, $table, $id, $fallback) {
  if ($id) { $st = $conn->prepare("SELECT name_lo FROM $table WHERE id = ? LIMIT 1"); $st->execute([$id]); $n=$st->fetchColumn(); if ($n!==false) return $n; }
  return $fallback ?: '';
}
function fmtDate($v) { return $v ? date('d/m/Y', strtotime($v)) : ''; }

$originName=resolveName($conn,'edoc_country',$row['origin']); $nationalityName=resolveName($conn,'edoc_country',$row['nationality']);
$ethnicityName=resolveName($conn,'edoc_ethnicity',$row['ethnicity']); $religionName=resolveName($conn,'edoc_religion',$row['religion']); $occupationName=resolveName($conn,'edoc_occupation',$row['occupation']);
$birthVillageName = geoName($conn,'edoc_village',$row['birth_village_id']??null,$row['birth_village']);
$birthTasengName  = geoName($conn,'edoc_taseng',$row['birth_taseng_id']??null,'');
$birthDistrictName = geoName($conn,'edoc_district',$row['birth_district_id']??null,$row['birth_district']);
$birthProvinceName = geoName($conn,'edoc_province',$row['birth_province_id']??null,$row['birth_province']);
// parent address: structured geo from new fields
function geoNameId($conn,$table,$id){ if($id){$st=$conn->prepare("SELECT name_lo FROM $table WHERE id=?");$st->execute([$id]);$n=$st->fetchColumn();if($n!==false)return $n;} return ''; }
$parentUnit      = $res['parents_unit_no'] ?? '';
$parentVillage   = geoNameId($conn,'edoc_village',$res['parents_village_id'] ?? null);
$parentTaseng    = geoNameId($conn,'edoc_taseng',$res['parents_taseng_id'] ?? null);
$parentDistrict  = geoNameId($conn,'edoc_district',$res['parents_district_id'] ?? null);
$parentProvince  = geoNameId($conn,'edoc_province',$res['parents_province_id'] ?? null);
$title=($row['gender']==='M')?'ທ້າວ':(($row['gender']==='F')?'ນາງ':'');
$today=date('d/m/Y');

// ── fill the official template (same routine as the generator) ──────
$template='/var/www/html/edoc/Doc_files/32 ໃບຢັ້ງຢືນທີ່ຢູ່ (1).docx';
if (!file_exists($template)) { die('Template not found'); }
$xml = (function() use ($template){ $z=new ZipArchive(); $z->open($template); $s=$z->getFromName('word/document.xml'); $z->close(); return $s; })();
$dom=new DOMDocument(); $dom->loadXML($xml);
$ns='http://schemas.openxmlformats.org/wordprocessingml/2006/main';

function wRuns($p){ $o=[]; foreach($p->childNodes as $c) if($c->nodeType===XML_ELEMENT_NODE && $c->localName==='r')$o[]=$c; return $o; }
function runText($r){ $s=''; foreach($r->childNodes as $c) if($c->localName==='t')$s.=$c->textContent; return $s; }
function setRunText($r,$t){ $first=null; foreach($r->childNodes as $c){ if($c->localName==='t'){ if($first===null)$first=$c; else $c->textContent=''; } } if($first!==null)$first->textContent=$t; }
function spliceAt($p,$absIdx,$OLen,$NEW){ $runs=wRuns($p); $pos=0;$start=null;$end=null;$ls=0;$le=0; foreach($runs as $i=>$r){ $t=runText($r);$rlen=strlen($t); if($start===null && $pos+$rlen>$absIdx){$start=$i;$ls=$absIdx-$pos;} if($pos+$rlen>=$absIdx+$OLen){$end=$i;$le=$absIdx+$OLen-$pos;break;} $pos+=$rlen; } if($start===null)return false; if($start===$end){ $t=runText($runs[$start]); setRunText($runs[$start],substr($t,0,$ls).$NEW.substr($t,$le)); } else { $t=runText($runs[$start]); setRunText($runs[$start],substr($t,0,$ls).$NEW); for($i=$start+1;$i<$end;$i++)setRunText($runs[$i],''); $te=runText($runs[$end]); setRunText($runs[$end],substr($te,$le)); } return true; }
function replacePhrase($p,$OLD,$NEW){ $full=''; foreach(wRuns($p) as $r)$full.=runText($r); $idx=strpos($full,$OLD); if($idx===false)return false; return spliceAt($p,$idx,strlen($OLD),$NEW); }
function fillDots($p,$label,$value){ if($value==='')return; $full=''; foreach(wRuns($p) as $r)$full.=runText($r); $pos=strpos($full,$label); if($pos===false)return; $len=strlen($full); $ws=$pos+strlen($label); while($ws<$len && $full[$ws]===' ')$ws++; $e=$ws; while($e<$len && $full[$e]==='.')$e++; if($e>$ws){ $ins=(trim(substr($full,$e))==='')?' '.$value:' '.$value.', '; spliceAt($p,$ws,$e-$ws,$ins); } }

// Title for the name line: keep the original "ທ້າວ ຫຼື ນາງ" wording if gender unknown.
// Name line: title+first_name, then a comma, then the ນາມສະກຸນ label + surname. No dots.
function fillNameLine($p, $titleFirst, $surname) {
  // 1. replace the placeholder phrase with title+first_name
  replacePhrase($p, 'ທ້າວ ຫຼື ນາງ', $titleFirst);
  // 2. collapse the dots (and any spaces) between the name and the ນາມສະກຸນ label into ', '
  $full = ''; foreach (wRuns($p) as $r) $full .= runText($r);
  $pos = strpos($full, 'ນາມສະກຸນ');
  if ($pos === false) return;
  $e = $pos;
  while ($e > 0 && ($full[$e - 1] === '.' || $full[$e - 1] === ' ')) $e--;
  spliceAt($p, $e, $pos - $e, ', ');
  // 3. collapse the dots after ນາມສະກຸນ into a single space before the surname
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
$nameTitle = ($title !== '') ? $title : 'ທ້າວ ຫຼື ນາງ';
// residence geo names from the citizen's CURRENT residence (not hardcoded).
// MUST be defined before $opsByAnchor below, or the array captures null and the
// requester's current-address ຕາແສງ/ບ້ານ/ເມືອງ/ແຂວງ render blank.
$resTaseng = $row['taseng_name'] ?: '';
$resDistrict = $row['district_name'] ?: '';
$resProvince = $row['province_name'] ?: '';
// edoc_village.name_lo may still carry the "ບ້ານ " prefix (only 42 villages in district 129 were
// cleaned in the DB); strip it so the template's own ບ້ານ label isn't duplicated.
$resVillage = preg_replace('/^\s*ບ້ານ\s*/u', '', ($row['village_name'] ?? ''));
$opsByAnchor=[
 '/ຄປຕ'=>[['dots','ເລກທີ',$row['doc_no']]],
 'PREFIXວັນທີ'=>[['dots','ວັນທີ',$today]],
 // Name line: put title+first_name in ONE phrase replacement. (Old code replaced the
 // phrase with the title first, then looked for label ທ້າວ to fill the first name —
 // which no longer existed for females, so ນາງ rows lost the first name.)
 'ທ້າວ ຫຼື ນາງ'=>[['name',trim($nameTitle.' '.$row['first_name']),$row['last_name']]],
 'ວັນ,ເດືອນ,ປີເກີດ'=>[['dots','ວັນ,ເດືອນ,ປີເກີດ',fmtDate($row['dob'])],['dots','ບ້ານເກີດ',$birthVillageName],['dots','ຕາແສງ',$birthTasengName],['dots','ເມືອງ',$birthDistrictName]],
 'ເຊື້ອຊາດ'=>[['dots','ແຂວງ',$birthProvinceName],['dots','ເຊື້ອຊາດ',$originName],['dots','ສັນຊາດ',$nationalityName],['dots','ສາສະໜາ',$religionName],['dots','ຊົນເຜົ່າ',$ethnicityName],['dots','ອາຊີບ',$occupationName],['dots','ບັດປະຈໍາຕົວເລກທີ',$row['id_card']],['dots','ລົງວັນທີ',fmtDate($row['id_card_issue_date'])]],
 'ເຮືອນເລກທີ'=>[['dots','ເຮືອນເລກທີ',$row['house_no']],['dots','ໜ່ວຍທີ',$row['unit_no']],['dots','ຕາແສງ',$resTaseng],['dots','ບ້ານ',$resVillage],['dots','ເມືອງ',$resDistrict],['dots','ແຂວງ',$resProvince]],
 'ສໍາມະໂນຄົວ'=>[['dots','ຂື້ນສໍາມະໂນຄົວເລກທີ',$res['household_book_no']??''],['dots','ລົງວັນທີ',fmtDate($res['household_book_date']??'')]],
 'ເບີໂທລະສັບ'=>[['dots','ເບີໂທລະສັບ',$row['phone']],['dots','ເປັນລູກຂອງທ້າວ',$res['father_name']??'']],
 'ແລະ ນາງ'=>[['dots','ແລະ ນາງ',$res['mother_name']??''],['dots','ໜ່ວຍ',$parentUnit],['dots','ຕາແສງ',$parentTaseng],['dots','ບ້ານ',$parentVillage]],
 // Parent address is split across two paragraphs: village/taseng (above) and ເມືອງ/ແຂວງ (the
 // following paragraph). Anchor the district/province fill on the ແຂວງ label so it lands in the
 // correct paragraph; otherwise those dots stay unfilled.
 'ແຂວງ'=>[['dots','ເມືອງ',$parentDistrict],['dots','ແຂວງ',$parentProvince]],
];

$paras=$dom->getElementsByTagNameNS($ns,'p');
$filled=[];
foreach($paras as $p){
  $full=''; foreach(wRuns($p) as $r)$full.=runText($r);
  $trimmed=ltrim($full);
  foreach($opsByAnchor as $anchor=>$ops){
    if(strpos($anchor,'PREFIX')===0){ if(strpos($trimmed,substr($anchor,6))!==0)continue; }
    else if(strpos($full,$anchor)===false)continue;
    foreach($ops as $op){ if($op[0]==='dots')fillDots($p,$op[1],$op[2]); else if($op[0]==='phrase')replacePhrase($p,$op[1],$op[2]); else if($op[0]==='name')fillNameLine($p,$op[1],$op[2]); }
    break;
  }
}

// ── replace the template's hardcoded district/taseng/province with the real residence names ──
// The official template has ເມືອງໄຊທານີ / ຕາແສງສີວິໄລ / ນະຄອນຫຼວງວຽງຈັນ baked in (and in the
// opening + ຕາງໜ້າ lines). Swap them with the citizen's actual current residence.
// NOTE: edoc_taseng.name_lo already carries the "ຕາແສງ " prefix and edoc_district.name_lo may carry
// "ເມືອງ ", so strip those so the template's own ຕາແສງ/ເມືອງ words aren't duplicated.
$resTasengRaw = $resTaseng;
$resDistrictRaw = $resDistrict;
$resTaseng    = preg_replace('/^\s*ຕາແສງ\s*/u', '', $resTaseng);
$resDistrict  = preg_replace('/^\s*ເມືອງ\s*/u', '', $resDistrict);
if ($resDistrict !== '') { replacePhraseAll($paras,'ໄຊທານີ',$resDistrict); }
if ($resTaseng   !== '') { replacePhraseAll($paras,'ສີວິໄລ',$resTaseng); }
if ($resProvince !== '') { replacePhraseAll($paras,'ນະຄອນຫຼວງວຽງຈັນ',$resProvince); }

function replacePhraseAll($paras,$OLD,$NEW){ foreach($paras as $p){ replacePhrase($p,$OLD,$NEW); } }

function pvClean($t){
  $t = preg_replace('/\s{2,}/', ' ', $t);   // collapse runs of whitespace to single space
  $t = preg_replace('/,\s*,/', ',', $t);    // collapse multiple commas
  $t = preg_replace('/,\s+$/', '', $t);     // trim trailing comma + spaces
  $t = preg_replace('/^\s+/', '', $t);      // trim leading
  return $t;
}
$xpath=new DOMXPath($dom); $xpath->registerNamespace('w',$ns);
// extract the Lao National Emblem image (image1.png) once for the preview header
$emblemB64 = '';
{
  $tmpE = tempnam(sys_get_temp_dir(),'emblem').'.png';
  $ze = new ZipArchive(); if ($ze->open($template) === true) { $img = $ze->getFromName('word/media/image1.png'); $ze->close(); if ($img !== false) { $emblemB64 = base64_encode($img); } }
  @unlink($tmpE);
}
$htmlParas=[];
$photoRendered=false;  // dedupe the repeated ຕິດຮູບ 3x4 boxes
$imgFound=false;       // only render the emblem once (first image-bearing para)
foreach($paras as $p){
  $text=''; foreach($xpath->query('.//w:t',$p) as $t)$text.=$t->textContent;
  $jc=$xpath->query('.//w:jc',$p)->item(0); $align=$jc?$jc->getAttribute('w:val'):'both';
  $hasEmblem = (!$imgFound) && ($xpath->query('.//*[local-name()="drawing"]',$p)->length>0 || $xpath->query('.//*[local-name()="pict"]',$p)->length>0);
  if ($hasEmblem) $imgFound = true;
  $t=pvClean($text);
  $rt='normal';
  // 0) national emblem (first image-bearing, empty-text para) — centered image
  if ($hasEmblem) { $rt='emblem'; }
  // 1) document title ໃບຢັ້ງຢືນທີ່ຢູ່ — bold + bigger
  else if ($t === 'ໃບຢັ້ງຢືນທີ່ຢູ່') { $rt='title'; }
  // 2) doc-reference line: ຕາແສງ... (left) + ເລກທີ .../ຄປຕ (right)
  else if (strpos($t,'/ຄປຕ')!==false || strpos($t,'/ຄປຕ ສວລ')!==false) { $rt='docref'; }
  // 3) photo block: keep ONE boxed, left-aligned (dedupe repeats)
  else if (strpos($t,'ຕິດຮູບ')!==false || strpos($t,'3x4')!==false) {
    if ($photoRendered) { $rt='skip'; } else { $rt='photo'; $photoRendered=true; }
  }
  // 4) opening line + name line: indent same
  else if (strpos($t,'ຄະນະກຳມະການປົກຄອງ')!==false && strpos($t,'ຂໍຢັ້ງຢືນ')!==false) { $rt='opening'; }
  else if (strpos($t,'ທ້າວ ')!==false && strpos($t,'ນາມສະກຸນ ')!==false) { $rt='opening'; }
  // 5) the two closing-content lines: ຊື່ງຜູ້ກ່ຽວ / ດັ່ງນັ້ນ — indent
  else if (strpos($t,'ຊື່ງຜູ້ກ່ຽວ')!==false || strpos($t,'ດັ່ງນັ້ນ, ຈຶ່ງ')!==false) { $rt='opening'; }
  // 6) signature block: ຕາງໜ້າ... / ປະທານ — 2/3 right, centered in that zone
  else if (strpos($t,'ຕາງໜ້າ ຄະນະ')!==false || $t === 'ປະທານ') { $rt='sign'; }
  $htmlParas[]=['text'=>$t,'align'=>$align,'rt'=>$rt];
}

require '../components/layout.php';
function pvAlign($align){ switch($align){case 'center':return 'center';case 'right':return 'right';default:return 'justify';} }
$labels=['SUBMITTED'=>'ຮັບແລ້ວ/submitted','PENDING_APPROVAL'=>'ລໍຖ້າອະນຸມັດ/Pending Approval','NEEDS_REVISION'=>'ຕ້ອງການປັບປຸງ/Needs Revision','ISSUED'=>'ອະນຸມັດແລ້ວ/Issued','PENDING_PAYMENT'=>'ລໍຖ້າຊໍາລະ/Pending Payment','PAID'=>'ຊໍາລະແລ້ວ/Paid','SIGNED'=>'ເຊັນແລ້ວ/Signed','HANDOVER'=>'ສົ່ງໃຫ້ແລ້ວ/Handover','DOWNLOADED'=>'ດາວໂຫລດແລ້ວ/Downloaded'];
?>
<style>
.preview-paper { background:#fff; border:1px solid #ddd; border-radius:8px; box-shadow:0 4px 18px rgba(0,0,0,.09); max-width:794px; margin:0 auto 20px; padding:44px 52px; font-family:'Noto Sans Lao','Phetsarath OT',serif; font-size:15px; line-height:1.9; }
.preview-paper p { margin:0 0 4px; }
.pv-docref { display:flex; justify-content:space-between; align-items:baseline; }
.pv-docref .pv-docref-left { text-align:left; }
.pv-docref .pv-docref-right { text-align:right; white-space:nowrap; font-weight:600; }
.pv-photo { float:left; border:1px solid #333; width:3cm; height:4cm; display:flex; flex-direction:column; align-items:center; justify-content:center; margin:0 16px 12px 0; font-size:13px; line-height:1.3; }
.pv-opening { clear:both; text-indent:1.27cm; }
.pv-title { text-align:center; font-weight:bold; font-size:16px; margin:55px 0 20px !important; }
.pv-emblem { text-align:center; margin:0 0 6px; }
.pv-emblem img { width:1.5cm; height:auto; }
.pv-sign { margin:20px 0 0 50% !important; text-align:center; font-size:14px; }
.pv-sign + .pv-sign { margin-top:0 !important; }
.pv-sign:last-of-type { margin-bottom:120px !important; }
.pv-amp { vertical-align:middle; }
.pv-vline { border:1px dashed #bbb; }
.pv-note { background:#fff8e1; border:1px solid #eed99a; color:#8a6d00; border-radius:8px; padding:10px 16px; max-width:820px; margin:0 auto 14px; font-size:.9rem; }
.pv-actions { max-width:820px; margin:0 auto 16px; }
@media print {
  @page { size: A4; margin: 15mm 17mm; }
  body { background:#fff !important; }
  .preview-paper { max-width:100%; border:none; box-shadow:none; border-radius:0; padding:0; margin:0; }
  .pv-note, .pv-actions, nav.breadcrumb, .content-wrapper > .breadcrumb { display:none !important; }
  .preview-paper p { break-inside: avoid; }
}
</style>
<div class="content-wrapper p-3">
  <nav class="breadcrumb mb-2">
    <ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="list_edoc_document.php">ບໍລິການເອກະສານ</a></li>
      <li class="breadcrumb-item"><a href="review_edoc_document.php?doc_id=<?= $doc_id ?>">ຈັດການ</a></li>
      <li class="breadcrumb-item active">ສະແດງຕົວຢ່າງ (Preview)</li>
    </ol>
  </nav>

  <div class="pv-actions text-center">
    <a class="btn btn-outline-secondary" href="review_edoc_document.php?doc_id=<?= $doc_id ?>"><i class="fas fa-arrow-left"></i> ກັບຄືນ (Back to review)</a>
    <button class="btn btn-outline-primary ms-2" onclick="window.print()"><i class="fas fa-print"></i> ພິມ (Print)</button>
    <a class="btn btn-outline-success ms-2" target="_blank" href="../generate/generate_edoc_document.php?doc_id=<?= $doc_id ?>"><i class="fas fa-file-word"></i> ດາວໂຫລດ Word (Download)</a>
  </div>

  <div class="pv-note"><i class="fas fa-info-circle"></i> ຕົວຢ່າງສະແດງຕາມແບບຟອມທາງການ (Preview of the official form) — ເອກະສານຈະອອກຢ່າງເປັນທາງການຫຼັງອະນຸມັດ ແລະ ຊຳລະເງິນ. ສະຖານະ: <?= $labels[$row['status']] ?? $row['status'] ?></div>

  <div class="preview-paper">
    <?php foreach ($htmlParas as $hp): ?>
      <?php if ($hp['rt'] === 'skip'): ?>
        <?php continue; ?>
      <?php elseif ($hp['rt'] === 'emblem'): ?>
        <?php if ($emblemB64 !== ''): ?>
          <div class="pv-emblem"><img src="data:image/png;base64,<?= $emblemB64 ?>" alt="ກາແຫ່ງຊາດ"></div>
        <?php endif; ?>
      <?php elseif ($hp['rt'] === 'photo'): ?>
        <div class="pv-photo">
          <div>ຕິດຮູບ</div>
          <div>3x4</div>
        </div>
      <?php elseif ($hp['rt'] === 'title'): ?>
        <p class="pv-title"><?= htmlspecialchars($hp['text']) ?></p>
      <?php elseif ($hp['rt'] === 'sign'): ?>
        <p class="pv-sign"><?= nl2br(htmlspecialchars($hp['text'])) ?></p>
      <?php elseif ($hp['rt'] === 'docref'): ?>
        <?php
          $t = $hp['text'];
          // split ຕາແສງ...  (left) from ເລກທີ .../ຄປຕ ສວລ  (right)
          $pos = strpos($t, 'ເລກທີ');
          $left = $pos !== false ? trim(substr($t, 0, $pos)) : $t;
          $right = $pos !== false ? trim(substr($t, $pos)) : '';
          $right = preg_replace('/,\s*$/', '', $right); // drop trailing comma from doc-ref
          $right = preg_replace('/\s*,?\s*(\/ຄປຕ)/', '\1', $right); // drop comma between doc-no and /ຄປຕ
        ?>
        <div class="pv-docref">
          <span class="pv-docref-left"><?= htmlspecialchars($left) ?></span>
          <?php if ($right !== ''): ?><span class="pv-docref-right"><?= htmlspecialchars($right) ?></span><?php endif; ?>
        </div>
      <?php elseif ($hp['rt'] === 'opening'): ?>
        <p class="pv-opening" style="text-align:<?= pvAlign($hp['align']) ?>"><?= nl2br(htmlspecialchars($hp['text'])) ?></p>
      <?php else: ?>
        <?php if (trim($hp['text']) === ''): ?>
          <div style="height:16px">&nbsp;</div>
        <?php else: ?>
          <p style="text-align:<?= pvAlign($hp['align']) ?>"><?= nl2br(htmlspecialchars($hp['text'])) ?></p>
        <?php endif; ?>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php require '../components/layout_footer.php'; ?>
