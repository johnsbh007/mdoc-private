<?php
/**
 * edoc_auth.php — central eDoc capability helper (auth/user-role gating)
 *
 * Capabilities are stashed into the session at login (see pages/login.php):
 *   can_create  — create/submit requests
 *   can_approve — approve / reject / request-change (approver/signatory)
 *   can_pay     — confirm & record payment, reject slip
 *   can_sign    — sign document (PAID -> SIGNED)
 *   can_issue   — handover / issue document
 *   can_delete  — delete requests
 *
 * Each accessor returns true when the current user holds the capability.
 * The admin dev-bypass and any role-1 user with write/delete bits get full access.
 * Missing session keys default to FALSE (fail closed) so an un-gated session
 * (e.g. an old session from before this feature) cannot perform restricted actions.
 */

function edoc_can($cap) {
    return isset($_SESSION[$cap]) && $_SESSION[$cap] === true;
}

function edoc_can_create()  { return edoc_can('can_create'); }
function edoc_can_approve() { return edoc_can('can_approve'); }
function edoc_can_pay()     { return edoc_can('can_pay'); }
function edoc_can_sign()    { return edoc_can('can_sign'); }
function edoc_can_issue()   { return edoc_can('can_issue'); }
function edoc_can_delete()  { return edoc_can('can_delete'); }
function edoc_can_basic_data()   { return edoc_can('can_basic_data'); }
function edoc_can_manage_users() { return edoc_can('can_manage_users'); }

/**
 * Which document items have a REAL official template + generator branch?
 * Everything else is "under construction": the generic EAV form works, but the
 * generated .docx would use the wrong template. Add item ids here as they are
 * implemented end-to-end. Currently implemented (verified live):
 *   1  = ໃບຢັ້ງຢືນທີ່ຢູ່ (residence certificate, doc_type 1, template 32)
 *   5  = ໃບຢັ້ງຢືນສາຍຍາດ (kinship certificate, doc_type 1, template 01-05)
 *   28 = ສັນຍາກູ້ຢືມເງິນ (loan contract, doc_type 6, template 06-28-02)
 *   31 = ສັນຍາຊື້-ຂາຍ ກຳມະສິດທີ່ດິນ (land sale contract, doc_type 6, template 06-31)
 *   52 = ໃບຢັ້ງຢືນຊື້-ຂາຍ ທີ່ດິນ (land sell-purchase, doc_type 14, template 31)
 */
function edoc_item_implemented($docItemId) {
    return in_array((int)$docItemId, [1, 5, 8, 28, 30, 52, 53], true);
}

/**
 * Require a capability for an AJAX action; on failure emit a JSON error and exit.
 * Returns true if allowed (caller may stop).
 */
function edoc_require_cap($cap, $actionLabel) {
    if (!edoc_can($cap)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'ທ່ານບໍ່ມີສິດດຳເນີນການ ('.$actionLabel.') / No permission for this action']);
        exit;
    }
    return true;
}
