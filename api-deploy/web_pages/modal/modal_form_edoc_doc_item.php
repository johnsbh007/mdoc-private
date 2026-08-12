<!-- D:\xampp\htdocs\account_web\modal\modal_form_edoc_doc_item.php -->
<div class="modal fade" id="modalFormDocItem" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg">
    <form id="formDocItem" method="POST" action="../insert/insert_edoc_doc_item.php">
      <div class="modal-content">
        <div class="modal-header" style="background-color: #044482; border-bottom: none;">
          <h4 class="modal-title" id="modalDocItemTitle" style="color: white; font-weight: 600; font-size: 1.15rem;">
            <i class="fas fa-file-alt"></i> ລາຍການເອກະສານ
          </h4>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" style="padding: 25px;">
          <input type="hidden" name="id" id="di_hidden_id" value="">

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">ປະເພດເອກະສານ <span class="text-danger">*</span></label>
              <select name="doc_type_id" id="doc_type_id" class="form-select" required>
                <option value="">-- ເລືອກປະເພດ --</option>
                <?php foreach ($docTypes as $t): ?>
                  <option value="<?= $t['id'] ?>">[<?= htmlspecialchars($t['doc_type_code']) ?>] <?= htmlspecialchars($t['name_lo']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">ລະຫັດລາຍການ</label>
              <input type="text" name="doc_item_code" id="doc_item_code" class="form-control" placeholder="01-01">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">ຊື່ (ພາສາລາວ) <span class="text-danger">*</span></label>
            <input type="text" name="name_lo" id="name_lo" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">ຊື່ (ພາສາອັງກິດ)</label>
            <input type="text" name="name_en" id="name_en" class="form-control">
          </div>

          <div class="row mb-3">
            <div class="col-md-4">
              <label class="form-label">ຫົວໜ່ວຍ</label>
              <input type="text" name="unit" id="unit" class="form-control" placeholder="ແຜ່ນ">
            </div>
            <div class="col-md-4">
              <label class="form-label">ລາຄາ (ກີບ)</label>
              <input type="number" step="0.01" name="price" id="price" class="form-control" value="0.00">
            </div>
            <div class="col-md-4">
              <label class="form-label">ສະຖານະ</label>
              <select name="is_active" id="is_active" class="form-select">
                <option value="1">ເປີດໃຊ້ງານ</option>
                <option value="0">ປິດ</option>
              </select>
            </div>
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