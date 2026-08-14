<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require_once '../connect.php';
$pageTitle = "ເລືອກປະເພດເອກະສານ - New Request";
$_current = 'select_doc_type.php';
include '../components/layout.php';

$types = $conn->query("SELECT id, doc_type_code, name_lo, name_en FROM edoc_doc_type WHERE is_active=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="content-wrapper p-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="list_edoc_document.php">ບໍລິການເອກະສານ</a></li>
        <li class="breadcrumb-item active" aria-current="page">ເລືອກປະເພດເອກະສານ</li>
      </ol>
    </nav>
  </div>

  <div class="card">
    <div class="card-header bg-primary text-white">ເລືອກປະເພດເອກະສານ / Select Document Type</div>
    <div class="card-body">
      <p class="text-muted">ກະລຸນາເລືອກປະເພດເອກະສານທີ່ຕ້ອງການ ເພື່ອປ້ອນຂໍ້ມູນ.</p>
      <div class="row g-3" id="typeGrid">
        <?php foreach ($types as $t): ?>
        <div class="col-md-4">
          <div class="card h-100 border type-card" style="cursor:pointer" data-type="<?= $t['id'] ?>"
               onclick="chooseType(<?= $t['id'] ?>)">
            <div class="card-body">
              <span class="badge bg-secondary mb-2">[<?= htmlspecialchars($t['doc_type_code']) ?>]</span>
              <h6 class="card-title mb-1"><?= htmlspecialchars($t['name_lo']) ?></h6>
              <small class="text-muted"><?= htmlspecialchars($t['name_en']) ?></small>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
function chooseType(typeId) {
  window.location.href = 'request_doc.php?doc_type_id=' + typeId;
}
</script>
<?php include '../components/layout_footer.php'; ?>
