<!-- D:\xampp\htdocs\account_web\modal\modal_form_edoc_expense_type.php -->
<div class="modal fade" id="modalFormExpenseType" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg">
    <form id="formExp" method="POST" action="../insert/insert_edoc_expense_type.php">
      <div class="modal-content">
        <div class="modal-header" style="background-color: #044482; border-bottom: none;">
          <h4 class="modal-title" id="modalExpTitle" style="color: white; font-weight: 600; font-size: 1.15rem;">
            <i class="fas fa-file-invoice-dollar"></i> ປະເພດລາຍຈ່າຍ (Expense)
          </h4>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" style="padding: 25px;">
          <input type="hidden" name="id" id="exp_hidden_id" value="">

          <div class="row mb-3">
            <div class="col-md-4">
              <label class="form-label">ລະຫັດ (expense_type) <span class="text-danger">*</span></label>
              <input type="text" name="expense_type" id="expense_type" class="form-control" required placeholder="SALARY">
            </div>
            <div class="col-md-4">
              <label class="form-label">ມາດຕາ (Article)</label>
              <input type="text" name="article_ref" id="article_ref" class="form-control" placeholder="18">
            </div>
            <div class="col-md-4">
              <label class="form-label">ຂໍ້ (Clause)</label>
              <input type="number" name="clause_no" id="clause_no" class="form-control" min="1" max="9" placeholder="1">
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

          <div class="mb-3">
            <label class="form-label">ສະຖານະ</label>
            <select name="is_active" id="is_active" class="form-select">
              <option value="1">ເປີດໃຊ້ງານ</option>
              <option value="0">ປິດ</option>
            </select>
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