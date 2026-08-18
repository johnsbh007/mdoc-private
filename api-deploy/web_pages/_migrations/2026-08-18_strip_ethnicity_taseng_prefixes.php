<?php
// 2026-08-18 — data cleanup: strip the baked-in Lao categorical prefixes from lookup name_lo
// so they don't duplicate the labels already present in the residence-cert template/preview.
//   edoc_ethnicity: 'ຊົນເຜົ່າ X'  -> 'X'   (50 rows)
//   edoc_taseng:    'ຕາແສງ X'     -> 'X'   (1408 rows)
// Run once on the server, then delete. Safe to re-run (idempotent).
require __DIR__ . '/../connect.php';
$conn->exec("SET NAMES utf8mb4");
$n1 = 0; foreach ($conn->query("SELECT DISTINCT name_lo FROM edoc_ethnicity WHERE name_lo LIKE 'ຊົນເຜົ່າ%'") as $r) {
  $v = $r['name_lo']; $new = preg_replace('/^ຊົນເຜົ່າ\s*/u','',$v); if ($new!==$v){ $conn->prepare("UPDATE edoc_ethnicity SET name_lo=? WHERE name_lo=?")->execute([$new,$v]); $n1++; }
}
$n2 = 0; foreach ($conn->query("SELECT DISTINCT name_lo FROM edoc_taseng WHERE name_lo LIKE 'ຕາແສງ%'") as $r) {
  $v = $r['name_lo']; $new = preg_replace('/^ຕາແສງ\s*/u','',$v); if ($new!==$v){ $conn->prepare("UPDATE edoc_taseng SET name_lo=? WHERE name_lo=?")->execute([$new,$v]); $n2++; }
}
echo "ethnicity={$n1} taseng={$n2}\n";
