<!-- D:\m-doc-taseng\api-deploy\web_pages\modal\modal_form_edoc_citizen.php -->
<div class="modal fade" id="modalFormCitizen" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg">
    <form id="formCitizen" method="POST" action="../insert/insert_edoc_citizen.php">
      <div class="modal-content">
        <div class="modal-header" style="background-color: #044482; border-bottom: none;">
          <h4 class="modal-title" id="modalCitizenTitle" style="color: white; font-weight: 600; font-size: 1.15rem;">
            <i class="fas fa-user"></i> ຂໍ້ມູນພົນລະເມືອງ
          </h4>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" style="padding: 25px;">
          <input type="hidden" name="id" id="cit_hidden_id" value="">

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">ຊື່ <span class="text-danger">*</span></label>
              <input type="text" name="first_name" id="cit_first" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">ນາມສະກຸນ <span class="text-danger">*</span></label>
              <input type="text" name="last_name" id="cit_last" class="form-control" required>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-4">
              <label class="form-label">ເພດ</label>
              <select name="gender" id="cit_gender" class="form-select">
                <option value="M">ຊາຍ</option>
                <option value="F">ຍິງ</option>
                <option value="">ບໍ່ລະບຸ</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">ວັນເດືອນປີເກີດ</label>
              <input type="date" name="dob" id="cit_dob" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">ເລກບັດປະຈຳຕົວ</label>
              <input type="text" name="id_card" id="cit_idcard" class="form-control">
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">ເບີໂທລະສັບ <span class="text-danger">*</span></label>
              <input type="text" name="phone" id="cit_phone" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">ອີເມວ (ທາງເລືອກ)</label>
              <input type="email" name="email" id="cit_email" class="form-control">
            </div>
          </div>

          <hr>
          <h6 class="text-muted mb-3">ທີ່ຢູ່ (Residence)</h6>
          <div class="row mb-3">
            <div class="col-md-3">
              <label class="form-label">ແຂວງ</label>
              <select name="province_id" id="cit_province" class="form-select" onchange="loadGeo({type:'province',id:this.value}, '#cit_district'); $('#cit_taseng').html('<option>--</option>'); $('#cit_village').html('<option>--</option>');">
                <option value="">-- ເລືອງ --</option>
                <?php
                $pv = $conn->query("SELECT id, name_lo FROM edoc_province ORDER BY prov_code ASC")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($pv as $p): ?>
                  <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name_lo']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">ເມືອງ</label>
              <select name="district_id" id="cit_district" class="form-select" onchange="loadGeo({type:'district',id:this.value}, '#cit_taseng'); $('#cit_village').html('<option>--</option>');">
                <option value="">-- ເລືອງ --</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">ຕາແສງ</label>
              <select name="taseng_id" id="cit_taseng" class="form-select" onchange="loadGeo({type:'taseng',id:this.value}, '#cit_village');">
                <option value="">-- ເລືອງ --</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">ບ້ານ</label>
              <select name="village_id" id="cit_village" class="form-select">
                <option value="">-- ເລືອງ --</option>
              </select>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">ບ້ານ/ເລກທີ່ບ້ານ, ຖະໜົນ (ຂໍ້ມູນເພີ່ມ)</label>
            <input type="text" name="address_line" id="cit_addr" class="form-control" placeholder="ເລກທີ່ 12, ບ້ານນາຕ້ອງ...">
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