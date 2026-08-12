<!-- D:\xampp\htdocs\account_web\modal\modal_form_edoc_district.php -->
<div class="modal fade" id="modalFormDistrict" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg">
    <form id="formDistrict" method="POST" action="../insert/insert_edoc_district.php">
      <div class="modal-content">
        <div class="modal-header" style="background-color: #044482; border-bottom: none;">
          <h4 class="modal-title" id="modalDistrictTitle" style="color: white; font-weight: 600; font-size: 1.15rem;">
            <i class="fas fa-map-marker-alt"></i> ເພີ່ມຂໍ້ມູນເມືອງໃໝ່ (eDoc)
          </h4>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" style="padding: 25px;">
          <input type="hidden" name="id" id="dist_hidden_id" value="">

          <div class="row mb-3">
            <div class="col-md-4">
              <label class="form-label">ແຂວງ <span class="text-danger">*</span></label>
              <select name="province_id" id="province_id" class="form-select" required>
                <option value="">-- ເລືອກແຂວງ --</option>
                <?php
                // We need to fetch provinces here - we'll pass them from the calling page via a global or just query
                // Since this is included by list_edoc_district.php which has $provinces, we can use it
                foreach ($provinces as $p): ?>
                  <option value="<?= $p['id'] ?>">[<?= htmlspecialchars($p['prov_code']) ?>] <?= htmlspecialchars($p['name_lo']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">ລະຫັດເມືອງ <span class="text-danger">*</span></label>
              <input type="text" name="dist_code" id="dist_code" class="form-control" required placeholder="0101">
            </div>
            <div class="col-md-4">
              <label class="form-label">Off ID</label>
              <input type="text" name="off_id" id="off_id" class="form-control" placeholder="00">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">ຊື່ເມືອງ (ພາສາລາວ) <span class="text-danger">*</span></label>
            <input type="text" name="dist_nm_l" id="dist_nm_l" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">ຊື່ເມືອງ (ພາສາອັງກິດ)</label>
            <input type="text" name="dist_nm_e" id="dist_nm_e" class="form-control">
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