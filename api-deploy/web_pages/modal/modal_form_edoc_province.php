<!-- D:\xampp\htdocs\account_web\modal\modal_form_edoc_province.php -->
<div class="modal fade" id="modalFormProvince" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg">
    <form id="formProvince" method="POST" action="../insert/insert_edoc_province.php">
      <div class="modal-content">
        <div class="modal-header" style="background-color: #044482; border-bottom: none;">
          <h4 class="modal-title" id="modalProvinceTitle" style="color: white; font-weight: 600; font-size: 1.15rem;">
            <i class="fas fa-map-marker-alt"></i> ເພີ່ມຂໍ້ມູນແຂວງໃໝ່ (eDoc)
          </h4>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" style="padding: 25px;">
          <input type="hidden" name="id" id="prov_hidden_id" value="">

          <div class="row mb-3">
            <div class="col-md-4">
              <label class="form-label">ລະຫັດແຂວງ <span class="text-danger">*</span></label>
              <input type="text" name="prov_code" id="prov_code" class="form-control" required placeholder="01">
            </div>
            <div class="col-md-8">
              <label class="form-label">Off ID (ສຳນັກງານ)</label>
              <input type="text" name="off_id" id="off_id" class="form-control" placeholder="00">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">ຊື່ແຂວງ (ພາສາລາວ) <span class="text-danger">*</span></label>
            <input type="text" name="prov_nm_l" id="prov_nm_l" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">ຊື່ແຂວງ (ພາສາອັງກິດ)</label>
            <input type="text" name="prov_nm_e" id="prov_nm_e" class="form-control">
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