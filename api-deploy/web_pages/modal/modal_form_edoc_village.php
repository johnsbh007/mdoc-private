<!-- D:\xampp\htdocs\account_web\modal\modal_form_edoc_village.php -->
<div class="modal fade" id="modalFormVillage" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg">
    <form id="formVillage" method="POST" action="../insert/insert_edoc_village.php">
      <div class="modal-content">
        <div class="modal-header" style="background-color: #044482; border-bottom: none;">
          <h4 class="modal-title" id="modalVillageTitle" style="color: white; font-weight: 600; font-size: 1.15rem;">
            <i class="fas fa-map-marker-alt"></i> ເພີ່ມຂໍ້ມູນບ້ານໃໝ່ (eDoc)
          </h4>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" style="padding: 25px;">
          <input type="hidden" name="id" id="vil_hidden_id" value="">

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">ຕາແສງ <span class="text-danger">*</span></label>
              <select name="tasseng_id" id="tasseng_id" class="form-select" required>
                <option value="">-- ເລືອກຕາແສງ --</option>
                <?php foreach ($tasengs as $t): ?>
                  <option value="<?= $t['id'] ?>">[<?= htmlspecialchars($t['prov_code']) ?>-<?= htmlspecialchars($t['dist_code']) ?>-<?= htmlspecialchars($t['tas_code']) ?>] <?= htmlspecialchars($t['tas_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">ລະຫັດບ້ານ <span class="text-danger">*</span></label>
              <input type="text" name="vil_code" id="vil_code" class="form-control" required placeholder="01010101">
            </div>
            <div class="col-md-3">
              <label class="form-label">nCode</label>
              <input type="text" name="ncode" id="ncode" class="form-control" placeholder="01010101">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">ຊື່ບ້ານ (ພາສາລາວ) <span class="text-danger">*</span></label>
            <input type="text" name="vil_nm_l" id="vil_nm_l" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">ຊື່ບ້ານ (ພາສາອັງກິດ)</label>
            <input type="text" name="vil_nm_e" id="vil_nm_e" class="form-control">
          </div>
        </div>

        <div class="modal-footer" style="background-color: #f8f9fa; border-top: 1px solid #eee; padding: 15px 25px;">
          <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> ຍົກເລີກ</button>
          <button type="submit" class="btn btn-success px-4"><i class="fas fa-save me-1"></i> ບັນທຶກ</button>
        </div>
      </div>
    </form>
  </div>
</div>