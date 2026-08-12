<!-- D:\xampp\htdocs\account_web\modal\modal_form_edoc_taseng.php -->
<div class="modal fade" id="modalFormTaseng" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg">
    <form id="formTaseng" method="POST" action="../insert/insert_edoc_taseng.php">
      <div class="modal-content">
        <div class="modal-header" style="background-color: #044482; border-bottom: none;">
          <h4 class="modal-title" id="modalTasengTitle" style="color: white; font-weight: 600; font-size: 1.15rem;">
            <i class="fas fa-map-marker-alt"></i> ເພີ່ມຂໍ້ມູນຕາແສງໃໝ່ (eDoc)
          </h4>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" style="padding: 25px;">
          <input type="hidden" name="id" id="tas_hidden_id" value="">

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">ເມືອງ <span class="text-danger">*</span></label>
              <select name="district_id" id="district_id" class="form-select" required>
                <option value="">-- ເລືອກເມືອງ --</option>
                <?php foreach ($districts as $d): ?>
                  <option value="<?= $d['id'] ?>">[<?= htmlspecialchars($d['prov_code']) ?>-<?= htmlspecialchars($d['dist_code']) ?>] <?= htmlspecialchars($d['dist_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">ລະຫັດຕາແສງ <span class="text-danger">*</span></label>
              <input type="text" name="tas_code" id="tas_code" class="form-control" required placeholder="010101">
            </div>
            <div class="col-md-3">
              <label class="form-label">nCode</label>
              <input type="text" name="ncode" id="ncode" class="form-control" placeholder="010101">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">ຊື່ຕາແສງ (ພາສາລາວ) <span class="text-danger">*</span></label>
            <input type="text" name="tas_nm_l" id="tas_nm_l" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">ຊື່ຕາແສງ (ພາສາອັງກິດ)</label>
            <input type="text" name="tas_nm_e" id="tas_nm_e" class="form-control">
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