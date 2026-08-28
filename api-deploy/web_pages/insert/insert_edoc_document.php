<?php
session_name('ACCOUNT_SATHA_SESS');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../pages/login.php"); exit; }
require_once '../connect.php';
require_once '../edoc_auth.php';
edoc_require_cap('can_create', 'ສ້າງ/ແກ້ໄຂຄຳຮ້ອງ');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doc_id      = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
        $citizen_id  = !empty($_POST['citizen_id']) ? (int)$_POST['citizen_id'] : 0;
        $doc_item_id = !empty($_POST['doc_item_id']) ? (int)$_POST['doc_item_id'] : 0;

        // hard gate: refuse inserts for document items without a real template yet
        if (!edoc_item_implemented($doc_item_id)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'ລາຍການເອກະສານນີ້ຍັງບໍ່ທັນພ້ອມໃຊ້ງານ (ກຳລັງພັດທະນາ) / This document item is under construction and cannot be submitted']);
            exit;
        }
    $village_id  = !empty($_POST['village_id']) ? (int)$_POST['village_id'] : null;
    $amount      = (float)($_POST['amount'] ?? 0);
    $remark      = trim($_POST['remark'] ?? '');
    $mode        = $_POST['mode'] ?? 'submit'; // submit | draft
    $off_id      = $_SESSION['off_id'] ?? '00';
    $created_by  = $_SESSION['user_id'];

    $manualFirst   = trim($_POST['manual_first_name'] ?? '');
    $manualLast    = trim($_POST['manual_last_name'] ?? '');
    $manualGender  = trim($_POST['manual_gender'] ?? '');
    $manualDob     = !empty($_POST['manual_dob']) ? $_POST['manual_dob'] : null;
    $manualPhone   = trim($_POST['manual_phone'] ?? '');
    $manualEmail   = trim($_POST['manual_email'] ?? '');
    $manualIdCard  = trim($_POST['manual_id_card'] ?? '');
    $manualAddress = trim($_POST['manual_address_line'] ?? '');

    $manualTitle       = trim($_POST['title'] ?? '');
    $manualNationality = trim($_POST['nationality'] ?? '');
    $manualOrigin      = trim($_POST['origin'] ?? '');
    $manualEthnicity   = trim($_POST['ethnicity'] ?? '');
    $manualReligion    = trim($_POST['religion'] ?? '');
    $manualOccupation  = trim($_POST['occupation'] ?? '');
    // 4-level geo codes (current address + birth place)
    $provinceId   = !empty($_POST['province_id'])   ? (int)$_POST['province_id']   : null;
    $districtId   = !empty($_POST['district_id'])   ? (int)$_POST['district_id']   : null;
    $tasengId     = !empty($_POST['taseng_id'])     ? (int)$_POST['taseng_id']     : null;
    $birthProvinceId = !empty($_POST['birth_province_id']) ? (int)$_POST['birth_province_id'] : null;
    $birthDistrictId = !empty($_POST['birth_district_id']) ? (int)$_POST['birth_district_id'] : null;
    $birthTasengId   = !empty($_POST['birth_taseng_id'])   ? (int)$_POST['birth_taseng_id']   : null;
    $birthVillageId  = !empty($_POST['birth_village_id'])  ? (int)$_POST['birth_village_id']  : null;
    $manualBirthVillage  = trim($_POST['birth_village'] ?? '');
    $manualBirthDistrict = trim($_POST['birth_district'] ?? '');
    $manualBirthProvince = trim($_POST['birth_province'] ?? '');
    $manualIdIssueDate = !empty($_POST['id_card_issue_date']) ? $_POST['id_card_issue_date'] : null;
    $manualHouseNo     = trim($_POST['house_no'] ?? '');
    $manualUnitNo      = trim($_POST['unit_no'] ?? '');

    // ── buyer (land-sale) — real citizen record keyed by national ID ──
    $bCitizenId  = !empty($_POST['buyer_citizen_id']) ? (int)$_POST['buyer_citizen_id'] : 0;
    $bIdCard     = trim($_POST['b_manual_id_card'] ?? '');
    $bFirst      = trim($_POST['b_first_name'] ?? '');
    $bLast       = trim($_POST['b_last_name'] ?? '');
    $bTitle      = trim($_POST['b_title'] ?? '');
    $bGender     = trim($_POST['b_gender'] ?? '');
    $bDob        = !empty($_POST['b_dob']) ? $_POST['b_dob'] : null;
    $bPhone      = trim($_POST['b_phone'] ?? '');
    $bEmail      = trim($_POST['b_email'] ?? '');
    $bNationality= trim($_POST['b_nationality'] ?? '');
    $bOrigin     = trim($_POST['b_origin'] ?? '');
    $bEthnicity  = trim($_POST['b_ethnicity'] ?? '');
    $bReligion   = trim($_POST['b_religion'] ?? '');
    $bOccupation = trim($_POST['b_occupation'] ?? '');
    $bIdIssueDate= !empty($_POST['b_id_card_issue_date']) ? $_POST['b_id_card_issue_date'] : null;
    $bHouseNo    = trim($_POST['b_house_no'] ?? '');
    $bUnitNo     = trim($_POST['b_unit_no'] ?? '');
    $bProvinceId = !empty($_POST['b_province_id']) ? (int)$_POST['b_province_id'] : null;
    $bDistrictId = !empty($_POST['b_district_id']) ? (int)$_POST['b_district_id'] : null;
    $bTasengId   = !empty($_POST['b_taseng_id']) ? (int)$_POST['b_taseng_id'] : null;
    $bVillageId  = !empty($_POST['b_village_id']) ? (int)$_POST['b_village_id'] : null;
    $bAddress    = trim($_POST['b_address_line'] ?? '');
    $bBirthProvinceId = !empty($_POST['b_birth_province_id']) ? (int)$_POST['b_birth_province_id'] : null;
    $bBirthDistrictId = !empty($_POST['b_birth_district_id']) ? (int)$_POST['b_birth_district_id'] : null;
    $bBirthTasengId   = !empty($_POST['b_birth_taseng_id']) ? (int)$_POST['b_birth_taseng_id'] : null;
    $bBirthVillageId  = !empty($_POST['b_birth_village_id']) ? (int)$_POST['b_birth_village_id'] : null;

    if (empty($doc_item_id)) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ກະລຸນາເລືອກເອກະສານ'];
        header("Location: ../pages/list_edoc_document.php"); exit;
    }

    if ($doc_id === 0 && empty($citizen_id) && ($manualFirst === '' || $manualLast === '' || $manualPhone === '')) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'ກະລຸນາເລືອກພົນລະເມືອງ ຫຼື ປ້ອນຂໍ້ມູນພົນລະເມືອງໃໝ່'];
        header("Location: ../pages/list_edoc_document.php"); exit;
    }

    $ok = false;
    $doc_no = null;
    try {
        $conn->beginTransaction();
        $existing = null;

        if ($doc_id > 0) {
            $lock = $conn->prepare("SELECT * FROM edoc_document WHERE id=? FOR UPDATE");
            $lock->execute([$doc_id]);
            $existing = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                throw new RuntimeException('ບໍ່ພົບຮ່າງຄຳຮ້ອງ');
            }
            if (!in_array($existing['status'], ['DRAFT', 'PENDING_APPROVAL', 'NEEDS_REVISION'], true)) {
                throw new RuntimeException('ຮ່າງຄຳຮ້ອງນີ້ບໍ່ສາມາດແກ້ໄຂໄດ້');
            }
            $citizen_id = $citizen_id ?: (int)$existing['citizen_id'];
            $doc_no = $existing['doc_no'];
        }

        // create or update citizen from manual walk-in data if provided
        if ($doc_id === 0 && empty($citizen_id)) {
            $full_name = trim($manualFirst . ' ' . $manualLast);
            $password_hash = password_hash(substr($manualPhone, -6) ?: 'changeme', PASSWORD_DEFAULT);
            $stmtCit = $conn->prepare("INSERT INTO edoc_citizen (phone, email, password_hash, full_name, title, first_name, last_name, id_card, village_id, province_id, district_id, taseng_id, address_line, dob, gender, nationality, origin, ethnicity, religion, occupation, birth_village, birth_district, birth_province, birth_village_id, birth_district_id, birth_taseng_id, birth_province_id, id_card_issue_date, house_no, unit_no, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)");
            $stmtCit->execute([$manualPhone, $manualEmail ?: null, $password_hash, $full_name, $manualTitle ?: null, $manualFirst, $manualLast, $manualIdCard ?: null, $village_id, $provinceId, $districtId, $tasengId, $manualAddress ?: null, $manualDob, $manualGender ?: null, $manualNationality ?: null, $manualOrigin ?: null, $manualEthnicity ?: null, $manualReligion ?: null, $manualOccupation ?: null, $manualBirthVillage ?: null, $manualBirthDistrict ?: null, $manualBirthProvince ?: null, $birthVillageId, $birthDistrictId, $birthTasengId, $birthProvinceId, $manualIdIssueDate, $manualHouseNo ?: null, $manualUnitNo ?: null]);
            $citizen_id = (int)$conn->lastInsertId();
        } elseif ($citizen_id > 0 && ($manualFirst !== '' || $manualLast !== '' || $manualPhone !== '')) {
            $full_name = trim(($manualFirst ?: '') . ' ' . ($manualLast ?: ''));
            $stmtCit = $conn->prepare("UPDATE edoc_citizen SET first_name=?, last_name=?, full_name=?, title=?, gender=?, dob=?, id_card=?, phone=?, email=?, village_id=?, province_id=?, district_id=?, taseng_id=?, address_line=?, nationality=?, origin=?, ethnicity=?, religion=?, occupation=?, birth_village=?, birth_district=?, birth_province=?, birth_village_id=?, birth_district_id=?, birth_taseng_id=?, birth_province_id=?, id_card_issue_date=?, house_no=?, unit_no=? WHERE id=?");
            $stmtCit->execute([
                $manualFirst ?: null,
                $manualLast ?: null,
                $full_name ?: null,
                $manualTitle ?: null,
                $manualGender ?: null,
                $manualDob ?: null,
                $manualIdCard ?: null,
                $manualPhone ?: null,
                $manualEmail ?: null,
                $village_id,
                $provinceId,
                $districtId,
                $tasengId,
                $manualAddress ?: null,
                $manualNationality ?: null,
                $manualOrigin ?: null,
                $manualEthnicity ?: null,
                $manualReligion ?: null,
                $manualOccupation ?: null,
                $manualBirthVillage ?: null,
                $manualBirthDistrict ?: null,
                $manualBirthProvince ?: null,
                $birthVillageId,
                $birthDistrictId,
                $birthTasengId,
                $birthProvinceId,
                $manualIdIssueDate,
                $manualHouseNo ?: null,
                $manualUnitNo ?: null,
                $citizen_id,
            ]);
        }

        // ── buyer citizen (land-sale): find by national ID or create/update ──
        $buyer_id = 0;
        if ($bFirst !== '' || $bLast !== '' || $bIdCard !== '' || $bPhone !== '') {
            // if we already have a resolved buyer id (edit reload or lookup), use it
            if ($bCitizenId > 0) {
                $buyer_id = $bCitizenId;
            } elseif ($bIdCard !== '') {
                $bf = $conn->prepare("SELECT id FROM edoc_citizen WHERE id_card=? AND is_active=1 LIMIT 1");
                $bf->execute([$bIdCard]);
                $buyer_id = (int)$bf->fetchColumn();
            }
            if ($buyer_id > 0) {
                // update existing buyer with the (possibly corrected) form data
                $bFull = trim($bFirst . ' ' . $bLast);
                $conn->prepare("UPDATE edoc_citizen SET first_name=?, last_name=?, full_name=?, title=?, gender=?, dob=?,
                                id_card=?, phone=?, email=?, village_id=?, province_id=?, district_id=?, taseng_id=?, address_line=?,
                                nationality=?, origin=?, ethnicity=?, religion=?, occupation=?, birth_village_id=?, birth_district_id=?,
                                birth_taseng_id=?, birth_province_id=?, id_card_issue_date=?, house_no=?, unit_no=? WHERE id=?")
                    ->execute([$bFirst ?: null, $bLast ?: null, $bFull ?: null, $bTitle ?: null, $bGender ?: null, $bDob,
                        $bIdCard ?: null, $bPhone ?: null, $bEmail ?: null, $bVillageId, $bProvinceId, $bDistrictId, $bTasengId, $bAddress ?: null,
                        $bNationality ?: null, $bOrigin ?: null, $bEthnicity ?: null, $bReligion ?: null, $bOccupation ?: null,
                        $bBirthVillageId, $bBirthDistrictId, $bBirthTasengId, $bBirthProvinceId, $bIdIssueDate, $bHouseNo ?: null, $bUnitNo ?: null,
                        $buyer_id]);
            } else {
                // create a new buyer citizen (keyed by national ID)
                $bFull = trim($bFirst . ' ' . $bLast);
                $bPw = password_hash(substr($bPhone ?: 'changeme', -6) ?: 'changeme', PASSWORD_DEFAULT);
                $conn->prepare("INSERT INTO edoc_citizen (phone, email, password_hash, full_name, title, first_name, last_name, id_card,
                                village_id, province_id, district_id, taseng_id, address_line, dob, gender, nationality, origin, ethnicity, religion,
                                occupation, birth_village_id, birth_district_id, birth_taseng_id, birth_province_id, id_card_issue_date, house_no, unit_no, is_active)
                                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)")
                    ->execute([$bPhone ?: null, $bEmail ?: null, $bPw, $bFull ?: null, $bTitle ?: null, $bFirst ?: null, $bLast ?: null, $bIdCard ?: null,
                        $bVillageId, $bProvinceId, $bDistrictId, $bTasengId, $bAddress ?: null, $bDob, $bGender ?: null, $bNationality ?: null, $bOrigin ?: null,
                        $bEthnicity ?: null, $bReligion ?: null, $bOccupation ?: null, $bBirthVillageId, $bBirthDistrictId, $bBirthTasengId, $bBirthProvinceId,
                        $bIdIssueDate, $bHouseNo ?: null, $bUnitNo ?: null]);
                $buyer_id = (int)$conn->lastInsertId();
            }
        }

        $doc_status = ($mode === 'draft') ? 'DRAFT' : 'PENDING_APPROVAL';
        $template_variant = (isset($_POST['template_variant']) && in_array($_POST['template_variant'], ['short', 'full'], true)) ? $_POST['template_variant'] : null;

        if ($doc_id > 0) {
            $stmt = $conn->prepare("UPDATE edoc_document
                                      SET citizen_id=?, buyer_id=?, doc_item_id=?, village_id=?, off_id=?, status=?, amount=?, template_variant=?, source='OFFICE', submitted_at=IF(?='PENDING_APPROVAL', COALESCE(submitted_at, NOW()), submitted_at)
                                    WHERE id=?");
            $stmt->execute([$citizen_id, $buyer_id ?: null, $doc_item_id, $village_id, $off_id, $doc_status, $amount, $template_variant, $doc_status, $doc_id]);
        } else {
            // sequence from max existing suffix for today (COUNT-based numbering collides after same-day deletes)
            $prefix = 'DOC-' . date('Ymd') . '-';
            $seq = (int)$conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(doc_no, 14) AS UNSIGNED)), 0) + 1 FROM edoc_document WHERE doc_no LIKE " . $conn->quote($prefix . '%'))->fetchColumn();
            $doc_no = $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
            $submitted_at_sql = ($doc_status === 'PENDING_APPROVAL') ? 'NOW()' : 'NULL';
            $stmt = $conn->prepare("INSERT INTO edoc_document (doc_no, citizen_id, buyer_id, doc_item_id, village_id, off_id, status, amount, template_variant, source, created_by, submitted_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,$submitted_at_sql)");
            $stmt->execute([$doc_no, $citizen_id, $buyer_id ?: null, $doc_item_id, $village_id, $off_id, $doc_status, $amount, $template_variant, 'OFFICE', $created_by]);
            $doc_id = (int)$conn->lastInsertId();
        }

        // replace form fields on every save so edits stay in sync
        $conn->prepare("DELETE FROM edoc_form_data WHERE doc_id=?")->execute([$doc_id]);
        foreach ($_POST as $k => $v) {
            if (strpos($k, 'field_') === 0) {
                $field_id = (int)substr($k, 6);
                $conn->prepare("INSERT INTO edoc_form_data (doc_id, field_id, field_value) VALUES (?,?,?)")->execute([$doc_id, $field_id, trim($v)]);
            }
        }

        // type-specific store: edoc_dc_residence (doc_type_id = 1)
        $docType = $conn->prepare("SELECT doc_type_id FROM edoc_doc_item WHERE id=?");
        $docType->execute([$doc_item_id]);
        $doc_type_id = (int)$docType->fetchColumn();
        if ($doc_type_id === 1 && $doc_item_id == 1) {
            $fieldset = $conn->prepare("SELECT id, field_name FROM edoc_doc_field WHERE doc_type_id=1");
            $fieldset->execute();
            $vals = [];
            foreach ($fieldset->fetchAll(PDO::FETCH_ASSOC) as $f) {
                $vals[$f['field_name']] = trim((string)($_POST['field_' . $f['id']] ?? ''));
            }
            $hbDate = !empty($vals['household_book_date']) ? $vals['household_book_date'] : null;
            $pUnit      = trim($_POST['parents_unit_no'] ?? '');
            $pProvince  = !empty($_POST['parents_province_id']) ? (int)$_POST['parents_province_id'] : null;
            $pDistrict  = !empty($_POST['parents_district_id']) ? (int)$_POST['parents_district_id'] : null;
            $pTaseng    = !empty($_POST['parents_taseng_id'])   ? (int)$_POST['parents_taseng_id']   : null;
            $pVillage   = !empty($_POST['parents_village_id'])  ? (int)$_POST['parents_village_id']  : null;
            $conn->prepare("DELETE FROM edoc_dc_residence WHERE doc_id=?")->execute([$doc_id]);
            $conn->prepare("INSERT INTO edoc_dc_residence (doc_id, household_book_no, household_book_date, father_name, mother_name, parents_address, purpose, parents_unit_no, parents_province_id, parents_district_id, parents_taseng_id, parents_village_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                 ->execute([
                    $doc_id,
                    $vals['household_book_no'] ?: null,
                    $hbDate,
                    $vals['father_name'] ?: null,
                    $vals['mother_name'] ?: null,
                    $vals['parents_address'] ?: null,
                    $vals['purpose'] ?: null,
                    $pUnit ?: null,
                    $pProvince,
                    $pDistrict,
                    $pTaseng,
                    $pVillage,
                 ]);
        }

        // invoice only for submitted records
        if ($doc_status === 'PENDING_APPROVAL') {
            // sequence from max existing suffix for today (COUNT-based numbering collides after same-day deletes)
            $invPrefix = 'INV-' . date('Ymd') . '-';
            $seq = (int)$conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(inv_no, 14) AS UNSIGNED)), 0) + 1 FROM edoc_invoice WHERE inv_no LIKE " . $conn->quote($invPrefix . '%'))->fetchColumn();
            $inv_no = $invPrefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
            $invStmt = $conn->prepare("SELECT COUNT(*) FROM edoc_invoice WHERE doc_id=?");
            $invStmt->execute([$doc_id]);
            if ((int)$invStmt->fetchColumn() === 0) {
                $conn->prepare("INSERT INTO edoc_invoice (doc_id, inv_no, amount, currency, status) VALUES (?,?,?,'LAK','UNPAID')")->execute([$doc_id, $inv_no, $amount]);
            }
        }

        // status history
        $conn->prepare("INSERT INTO edoc_document_status (doc_id, status, remark, changed_by) VALUES (?, ?, ?, ?)")
              ->execute([$doc_id, $doc_status, $remark ?: ($doc_status === 'DRAFT' ? 'ບັນທຶກເປັນຮ່າງ' : 'ສົ່ງຄຳຮ້ອງ'), $created_by]);

        $conn->commit();
        $ok = true;
        $msg = ($doc_status === 'DRAFT') ? 'ບັນທຶກຮ່າງຄຳຮ້ອງສຳເລັດ!' : 'ບັນທຶກຄຳຮ້ອງສຳເລັດ!';
        $_SESSION['alert'] = ['type' => 'success', 'message' => $msg];
    } catch (Throwable $e) {
        $conn->rollBack();
        $msg = 'ຜິດພາດ: ' . $e->getMessage();
        $_SESSION['alert'] = ['type' => 'error', 'message' => $msg];
    }

    // For AJAX (X-Requested-With or JSON accept) return JSON so the UI can react; otherwise redirect.
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status'   => $ok ? 'success' : 'error',
            'message'  => $msg,
            'doc_id'   => $doc_id ?? null,
            'doc_no'   => $doc_no ?? null,
        ]);
        exit;
    }
    header("Location: ../pages/list_edoc_document.php"); exit;
}
