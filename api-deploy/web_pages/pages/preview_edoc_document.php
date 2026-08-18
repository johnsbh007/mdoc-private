<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require_once '../connect.php';

$doc_id = (int)($_GET['doc_id'] ?? 0);
if ($doc_id <= 0) { die('Missing doc_id'); }

// ── load doc + citizen + residence ───────────────────────────────────
$d = $conn->prepare("SELECT d.*, c.*, v.name_lo AS village_name,
                            i.name_lo AS item_name, t.name_lo AS type_name, i.doc_type_id
                     FROM edoc_document d
                     JOIN edoc_citizen c ON d.citizen_id = c.id
                     JOIN edoc_doc_item i ON d.doc_item_id = i.id
                     JOIN edoc_doc_type t ON i.doc_type_id = t.id
                     LEFT JOIN edoc_village v ON c.village_id = v.id
                     WHERE d.id = ?");
$d->execute([$doc_id]);
$row = $d->fetch(PDO::FETCH_ASSOC);
if (!$row) { die('Document not found'); }
if ((int)$row['doc_type_id'] !== 1) { die('Preview available for Residence Certificate only'); }

$res = null;
$rs = $conn->prepare("SELECT * FROM edoc_dc_residence WHERE doc_id = ?");
$rs->execute([$doc_id]);
$res = $rs->fetch(PDO::FETCH_ASSOC) ?: [];

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
$birthVillageName=geoName($conn,'edoc_village',$row['birth_village_id']??null,$row['birth_village']); $birthDistrictName=geoName($conn,'edoc_district',$row['birth_district_id']??null,$row['birth_district']); $birthProvinceName=geoName($conn,'edoc_province',$row['birth_province_id']??null,$row['birth_province']);
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
function fillDots($p,$label,$value){ if($value==='')return; $full=''; foreach(wRuns($p) as $r)$full.=runText($r); $pos=strpos($full,$label); if($pos===false)return; $len=strlen($full); $ws=$pos+strlen($label); while($ws<$len && $full[$ws]===' ')$ws++; $e=$ws; while($e<$len && $full[$e]==='.')$e++; if($e>$ws)spliceAt($p,$ws,$e-$ws,$value); }

$opsByAnchor=[
 '/ຄປຕ'=>[['dots','ເລກທີ',$row['doc_no']]],
 'PREFIXວັນທີ'=>[['dots','ວັນທີ',$today]],
 'ທ້າວ ຫຼື ນາງ'=>[['phrase','ທ້າວ ຫຼື ນາງ',$title],['dots','ທ້າວ',$row['first_name']],['dots','ນາມສະກຸນ',$row['last_name']]],
 'ວັນ,ເດືອນ,ປີເກີດ'=>[['dots','ວັນ,ເດືອນ,ປີເກີດ',fmtDate($row['dob'])],['dots','ບ້ານເກີດ',$birthVillageName],['dots','ເມືອງ',$birthDistrictName]],
 'ເຊື້ອຊາດ'=>[['dots','ແຂວງ',$birthProvinceName],['dots','ເຊື້ອຊາດ',$originName],['dots','ສັນຊາດ',$nationalityName],['dots','ສາສະໜາ',$religionName],['dots','ຊົນເຜົ່າ',$ethnicityName],['dots','ອາຊີບ',$occupationName],['dots','ບັດປະຈໍາຕົວເລກທີ',$row['id_card']],['dots','ລົງວັນທີ',fmtDate($row['id_card_issue_date'])]],
 'ເຮືອນເລກທີ'=>[['dots','ເຮືອນເລກທີ',$row['house_no']],['dots','ໜ່ວຍທີ',$row['unit_no']],['dots','ບ້ານ',$row['village_name']]],
 'ສໍາມະໂນຄົວ'=>[['dots','ຂື້ນສໍາມະໂນຄົວເລກທີ',$res['household_book_no']??''],['dots','ລົງວັນທີ',fmtDate($res['household_book_date']??'')]],
 'ເບີໂທລະສັບ'=>[['dots','ເບີໂທລະສັບ',$row['phone']],['dots','ເປັນລູກຂອງທ້າວ',$res['father_name']??'']],
 'ແລະ ນາງ'=>[['dots','ແລະ ນາງ',$res['mother_name']??''],['dots','ບ້ານ',$res['parents_address']??'']],
];
$paras=$dom->getElementsByTagNameNS($ns,'p');
$filled=[];
foreach($paras as $p){
  $full=''; foreach(wRuns($p) as $r)$full.=runText($r);
  $trimmed=ltrim($full);
  foreach($opsByAnchor as $anchor=>$ops){
    if(strpos($anchor,'PREFIX')===0){ if(strpos($trimmed,substr($anchor,6))!==0)continue; }
    else if(strpos($full,$anchor)===false)continue;
    foreach($ops as $op){ if($op[0]==='dots')fillDots($p,$op[1],$op[2]); else if($op[0]==='phrase')replacePhrase($p,$op[1],$op[2]); }
    break;
  }
}

// collect filled paragraphs + alignment for HTML rendering
$xpath=new DOMXPath($dom); $xpath->registerNamespace('w',$ns);
$htmlParas=[];
foreach($paras as $p){
  $text=''; foreach($xpath->query('.//w:t',$p) as $t)$text.=$t->textContent;
  $jc=$xpath->query('.//w:jc',$p)->item(0); $align=$jc?$jc->getAttribute('w:val'):'both';
  $htmlParas[]=['text'=>$text,'align'=>$align];
}

require '../components/layout.php';
function pvAlign($align){ switch($align){case 'center':return 'center';case 'right':return 'right';default:return 'justify';} }
$labels=['SUBMITTED'=>'ຮັບແລ້ວ/submitted','PENDING_APPROVAL'=>'ລໍຖ້າອະນຸມັດ/Pending Approval','NEEDS_REVISION'=>'ຕ້ອງການປັບປຸງ/Needs Revision','ISSUED'=>'ອະນຸມັດແລ້ວ/Issued','PENDING_PAYMENT'=>'ລໍຖ້າຊໍາລະ/Pending Payment','PAID'=>'ຊໍາລະແລ້ວ/Paid','SIGNED'=>'ເຊັນແລ້ວ/Signed','HANDOVER'=>'ສົ່ງໃຫ້ແລ້ວ/Handover','DOWNLOADED'=>'ດາວໂຫລດແລ້ວ/Downloaded'];
?>
<style>
.preview-paper { background:#fff; border:1px solid #ddd; border-radius:8px; box-shadow:0 4px 18px rgba(0,0,0,.09); max-width:820px; margin:0 auto 20px; padding:44px 52px; font-family:'Noto Sans Lao','Phetsarath OT',serif; font-size:15px; line-height:1.9; }
.preview-paper p { margin:0 0 4px; }
.pv-amp { vertical-align:middle; }
.pv-vline { border:1px dashed #bbb; }
.pv-note { background:#fff8e1; border:1px solid #eed99a; color:#8a6d00; border-radius:8px; padding:10px 16px; max-width:820px; margin:0 auto 14px; font-size:.9rem; }
.pv-actions { max-width:820px; margin:0 auto 16px; }
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
      <?php if (trim($hp['text']) === ''): ?>
        <div style="height:16px">&nbsp;</div>
      <?php else: ?>
        <p style="text-align:<?= pvAlign($hp['align']) ?>"><?= nl2br(htmlspecialchars($hp['text'])) ?></p>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php require '../components/layout_footer.php'; ?>
