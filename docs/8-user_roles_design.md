# e-Doc — User Roles & Permissions (Design Doc)

**Project**: Electronic Document service for Tasseng offices (eDoc web + mDoc citizen mobile)
**Database**: `edoc_tasaeng` (MySQL, apis.com.la)
**Status**: DESIGN — pending team confirmation, then implementation
**Date**: 2026-08-22
**Relates to**: `docs/4-php_app_structure.md` (§5 Auth/roles), `docs/7-request_lifecycle_flow.md`,
commit `77c972d` (capability-bit gating, live)

---

## 1. Purpose

Define the three staff roles for the eDoc web application:

| Role | Lao | English | Who |
|---|---|---|---|
| **Officer** | ພະນັກງານ | Officer / clerk | Tasseng staff doing the daily work |
| **Approver** | ຜູ້ອະນຸມັດ | Approver / signatory | Head of office / authorised approver |
| **System Admin** | ຜູ້ດູແລລະບົບ | System Administrator | Technical/system manager |

This document defines what each role can and cannot do, how roles are stored and
enforced, and the migration path from today's capability-bit build to role-based control.

---

## 2. Role Definitions

### 2.1 Officer (ພະນັກງານ)
The day-to-day worker. Handles citizens, enters requests, follows the workflow up to the
point where an official decision is required.

**Can:**
- Create new requests (draft), edit drafts, submit them (→ PENDING_APPROVAL)
- View all requests (list + review), search, use the status queues in the sidebar
- Upload payment slip (citizen pays at the counter / shows transfer evidence)
- Confirm/reject payment (record the fee is paid → PAID)
- Record handover (recipient name/ID/date) — but only AFTER the approver has signed
- Generate + download the issued document (the officer prepares the certificate file)

**Cannot:**
- Approve / reject / request-change at the approval stage (approver's decision)
- Sign the document (approver/signatory's act)
- Delete requests
- Manage system data (doc types/items, lookups, users, roles)

### 2.2 Approver (ຜູ້ອະນຸມັດ)
The decision-maker/signatory. Reviews submitted requests, approves or sends back,
and signs the issued document.

**Can:**
- View all requests
- Approve (PENDING_APPROVAL → ISSUED)
- Reject (→ REJECTED) with reason
- Request change (→ NEEDS_REVISION)
- Sign the document (PAID → SIGNED) — officer may then do the handover
- Generate + download the issued document (needs the artifact to sign/print)

**Cannot:**
- Create/edit/submit requests (data entry stays with the officer)
- Record payment / upload slip (conflict of interest — officer records at counter)
- Delete requests
- Manage system data

### 2.3 System Admin (ຜູ້ດູແລລະບົບ)
Owner of the **system**, not the **data**. Manages master data, user accounts, roles,
and technical configuration. Does NOT process citizen documents.

**Can:**
- Everything under ຂໍ້ມູນພື້ນຖານ (Basic Data): geo hierarchy, doc types/items, fee/expense types
- Manage users & assign roles (manage roles/users — future page, or via DB)
- View all requests (read-only oversight)
- Delete requests (administrative cleanup — e.g. discarded drafts/test data)
- Generate/download issued documents for troubleshooting

**Cannot:**
- Create/edit/submit citizen requests (no data entry)
- Approve/reject/sign citizen documents (not the approver)
- Record payments (not the counter)

> Note: today's single `admin` dev-bypass user acts as both System Admin AND a
> full-power tester. This is a deliberate DEV convenience. In production, the roles
> above separate duties. (Open question §6.1 about whether the top admin also holds
> a "supervisor override" over officer/approver restrictions.)

---

## 3. Permission Matrix

Legend: ✓ allowed · ✗ not allowed · (✓) allowed under listed status conditions

| Action | Officer | Approver | System Admin |
|---|---|---|---|
| View list/review (all statuses) | ✓ | ✓ | ✓ |
| Create draft / edit draft / re-submit | ✓ | ✗ | ✗ |
| Submit (→ PENDING_APPROVAL) | ✓ | ✗ | ✗ |
| Upload payment slip (ISSUED/PENDING_PAYMENT) | ✓ | ✗ | ✗ |
| Confirm paid / reject slip (PENDING_PAYMENT) | ✓ | ✗ | ✗ |
| Approve (PENDING_APPROVAL → ISSUED) | ✗ | ✓ | ✗ |
| Reject (→ REJECTED) | ✗ | ✓ | ✗ |
| Request change (→ NEEDS_REVISION) | ✗ | ✓ | ✗ |
| Sign (PAID → SIGNED) | ✗ | ✓ | ✗ |
| Handover — enter recipient (SIGNED) | ✓ | ✗ | ✓ (admin oversight may record) |
| Generate/store issued document | ✓ | ✓ | ✓ |
| Download issued document | ✓ | ✓ | ✓ |
| Delete request | ✗ | ✗ | ✓ |
| Basic Data CRUD (geo, doc types/items, fees) | ✗ | ✗ | ✓ |
| Manage users/roles | ✗ | ✗ | ✓ |
| Reports (future menu) | ✓ (own office?) | ✓ | ✓ |

---

## 4. Storage Model

### 4.1 Principle: keep the colleague's `ap_users` untouched (write), read-only use

`ap_users` is the shared staff table (all apps). We must NOT alter its columns or
semantics for other apps. Two compatible options:

**Option A (recommended): dedicated `edoc_user_role` table (our own, `edoc_*`)**

```sql
CREATE TABLE edoc_user_role (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      BIGINT UNSIGNED NOT NULL COMMENT 'FK ap_users.id (read-only ref)',
  role         VARCHAR(20) NOT NULL COMMENT 'officer | approver | system_admin',
  off_id       VARCHAR(10) DEFAULT NULL COMMENT 'Scope: NULL=all assigned offices of the user',
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_edoc_user_role (user_id),
  KEY idx_edoc_user_role_off (off_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='eDoc role assignment per ap_users user';
```

- `user_id` references `ap_users.id` — READ ONLY, never writes to ap_users.
- One row per user (UNIQUE user_id); role is the single authority for eDoc actions.
- If no row exists for a user: **deny restricted actions** (fail closed = viewer/read-only).
- `off_id` scope: when set, the role applies only to that office; NULL = the user's
  office assignments from `ap_user_departments` apply as usual (can_view etc.).

**Option B: extend `ap_users`** — forbidden by our scope rule (colleague's table).
Not considered further.

### 4.2 Mapping to today's capability bits (commit 77c972d)

The current live build gates on session capability booleans (`can_create`,
`can_approve`, `can_pay`, `can_sign`, `can_issue`, `can_delete`) derived at login
from `ap_users.write_bit/delete_bit`. The role model REPLACES that derivation:

| Capability(session) | officer | approver | system_admin |
|---|---|---|---|
| can_create  | ✓ | ✗ | ✗ |
| can_approve | ✗ | ✓ | ✗ |
| can_pay     | ✓ | ✗ | ✗ |
| can_sign    | ✗ | ✓ | ✗ |
| can_issue   | ✓ | ✗ | ✓ |
| can_delete  | ✗ | ✗ | ✓ |
| can_basic_data | ✗ | ✗ | ✓ |
| can_manage_users | ✗ | ✗ | ✓ |

The `edoc_auth.php` helper stays; only the login-time source of truth changes from
`ap_users` bits to `edoc_user_role.role`.

---

## 5. Implementation Plan (after approval)

1. **Migration** `_migrations/2026-08-22_add_edoc_user_role.php`
   - create `edoc_user_role` (idempotent)
   - seed from existing users: admin → system_admin; sayaka/a/pmu → officer
     (or as per team decision §6.2)
2. **Login** (`pages/login.php`, server-managed):
   - after auth, look up `edoc_user_role` and set the session caps per §4.2 table
   - keep the admin dev-bypass fully-privileged (test convenience)
3. **`edoc_auth.php`** — unchanged API; add `edoc_can_basic_data()`,
   `edoc_can_manage_users()`; keep fail-closed semantics
4. **Handlers** — already wired (commit 77c972d); no logic change needed except
   `insert`/`process`/`delete` keep their current `edoc_require_cap` gates
5. **UI hiding** — already wired for approve/reject/delete/create/pay/sign/issue;
   extend Basic Data pages with `edoc_can_basic_data()` guard (currently open to all)
6. **Optional**: admin manage-users page (assign roles) — later iteration
7. **Docs**: update §5 of `docs/4-php_app_structure.md`, commit log entries

---

## 6. Open Questions (need team decision)

### 6.1 Does System Admin also act as supervisor override?
Today's `admin` dev-bypass approves/pays/deletes — i.e. behaves like officer+approver+admin.
In production, should the top admin be able to do EVERYTHING (including process documents,
approve, pay) as an override, or strictly system-only (no data processing)?

### 6.2 Which real users map to which roles?
Current `ap_users`: admin(1) full bits, sayaka(2)/a(3) no bits, pmu(4) role 3.
Proposed seed: admin → system_admin; others → officer. Confirm.

### 6.3 Office scoping
Should the Approver at a tasseng office see only that office's requests, or all?
(There is per-user office access via `ap_user_departments` today; the list page is
currently office-scoped by session `off_id`?)

### 6.4 Reports visibility (future)
Officer sees own-office reports only; System Admin sees national roll-up? (Decide when
reports are built.)

---

## 7. Related Files

- `docs/4-php_app_structure.md` — §5 staff auth/roles baseline
- `docs/7-request_lifecycle_flow.md` — workflow & status definitions
- `edoc_auth.php` — live capability helper (api-deploy/web_pages/)
- `pages/login.php` — server-managed login (session source of truth)
- Commit `77c972d` — capability-bit gating now live