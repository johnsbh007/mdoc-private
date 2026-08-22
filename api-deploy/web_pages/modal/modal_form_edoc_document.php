<!-- D:\m-doc-taseng\api-deploy\web_pages\modal\modal_form_edoc_document.php -->
<div class="modal fade" id="modalFormDoc" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg">
    <form id="formDoc" method="POST" action="../insert/insert_edoc_document.php">
      <input type="hidden" name="mode" id="doc_mode" value="submit">
      <div class="modal-content">
        <div class="modal-header" style="background-color: #044482; border-bottom: none;">
          <h4 class="modal-title" id="modalDocTitle" style="color: white; font-weight: 600; font-size: 1.15rem;">
            <i class="fas fa-file-signature"></i> ຄຳຮ້ອງໃໝ່ (Officer Entry)
          </h4>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" style="padding: 25px;">
          <input type="hidden" name="id" id="doc_hidden_id" value="">

          <div class="alert alert-info py-2 mb-3">
            <strong>2 ສະຖານະການ:</strong>
            ເລືອກພົນລະເມືອງທີ່ມີຢູ່ແລ້ວ ຫຼື ປ້ອນຂໍ້ມູນພົນລະເມືອງໃໝ່ແນບໄວ້ໃນຮ່າງ.
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">ພົນລະເມືອງທີ່ມີຢູ່ແລ້ວ</label>
              <select name="citizen_id" id="doc_citizen" class="form-select">
                <option value="">-- ເລືອກພົນລະເມືອງ --</option>
                <?php
                $cz = $conn->query("SELECT id, first_name, last_name, phone FROM edoc_citizen WHERE is_active=1 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cz as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?> (<?= htmlspecialchars($c['phone']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">ປະເພດເອກະສານ / ລາຍການ <span class="text-danger">*</span></label>
              <select name="doc_item_id" id="doc_item" class="form-select" required onchange="loadDocFields()">
                <option value="">-- ເລືອງ --</option>
                <?php
                $its = $conn->query("SELECT di.id, di.doc_item_code, di.name_lo, di.price, dt.name_lo AS type_name FROM edoc_doc_item di JOIN edoc_doc_type dt ON di.doc_type_id=dt.id WHERE di.is_active=1 ORDER BY di.doc_item_code ASC")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($its as $it): ?>
                  <option value="<?= $it['id'] ?>" data-price="<?= $it['price'] ?>">[<?= htmlspecialchars($it['doc_item_code']) ?>] <?= htmlspecialchars($it['name_lo']) ?> (<?= htmlspecialchars($it['type_name']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="border rounded p-3 mb-3" style="background:#fbfdff;">
            <div class="fw-semibold mb-2"><i class="fas fa-user-plus"></i> ຂໍ້ມູນພົນລະເມືອງໃໝ່ / Walk-in</div>
            <div class="row mb-2">
              <div class="col-md-6">
                <label class="form-label">ຊື່</label>
                <input type="text" name="manual_first_name" id="manual_first_name" class="form-control" placeholder="ຊື່">
              </div>
              <div class="col-md-6">
                <label class="form-label">ນາມສະກຸນ</label>
                <input type="text" name="manual_last_name" id="manual_last_name" class="form-control" placeholder="ນາມສະກຸນ">
              </div>
            </div>
            <div class="row mb-2">
              <div class="col-md-4">
                <label class="form-label">ເພດ</label>
                <select name="manual_gender" id="manual_gender" class="form-select">
                  <option value="">-- ເລືອກ --</option>
                  <option value="M">M</option>
                  <option value="F">F</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">ວັນເກີດ</label>
                <input type="date" name="manual_dob" id="manual_dob" class="form-control">
              </div>
              <div class="col-md-4">
                <label class="form-label">ເບີໂທ</label>
                <input type="text" name="manual_phone" id="manual_phone" class="form-control" placeholder="20xxxxxxxx">
              </div>
            </div>
            <div class="row mb-2">
              <div class="col-md-6">
                <label class="form-label">ເລກບັດປະຈຳຕົວ</label>
                <input type="text" name="manual_id_card" id="manual_id_card" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label">ອີເມວ</label>
                <input type="email" name="manual_email" id="manual_email" class="form-control">
              </div>
            </div>
            <div class="row mb-2">
              <div class="col-md-6">
                <label class="form-label">ບ້ານ/ທີ່ຢູ່</label>
                <select name="village_id" id="doc_village" class="form-select">
                  <option value="">-- ບ້ານຕາແສງ --</option>
                  <?php
                  $vs = $conn->query("SELECT v.id, v.name_lo, t.name_lo AS tn FROM edoc_village v JOIN edoc_taseng t ON v.tasseng_id=t.id ORDER BY v.id ASC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
                  foreach ($vs as $v): ?>
                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name_lo']) ?> (<?= htmlspecialchars($v['tn']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">ທີ່ຢູ່ລາຍລະອຽດ</label>
                <input type="text" name="manual_address_line" id="manual_address_line" class="form-control">
              </div>
            </div>
            <div class="form-text">ປ້ອນຂໍ້ມູນນີ້ເມື່ອພົນລະເມືອງຍັງບໍ່ມີໃນລະບົບ.</div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="label">ຈຳນວນເງິນ (ກີບ)</label>
              <input type="number" step="0.01" name="amount" id="doc_amount" class="form-control" value="0.00">
            </div>
            <div class="col-md-6">
              <label class="form-label">ສະຖານະເຊົ້າຮ່າງ</label>
              <div class="form-control bg-light" id="doc_status_hint">PENDING_APPROVAL</div>
            </div>
          </div>

          <div id="doc_fields_wrap"></div>

          <div class="mb-2">
            <label class="form-label">ໝາຍເຫດ (Remark)</label>
            <textarea name="remark" id="doc_remark" class="form-control" rows="2"></textarea>
          </div>
        </div>

        <div class="modal-footer" style="background-color: #f8f9fa; border-top: 1px solid #eee; padding: 15px 25px;">
          <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> ຍົກເລີກ</button>
          <button type="submit" class="btn btn-success px-4" id="submitDocBtn"><i class="fas fa-save me-1"></i> ບັນທຶກຄຳຮ້ອງ</button>
        </div>
      </div>
    </form>
  </div>
</div>