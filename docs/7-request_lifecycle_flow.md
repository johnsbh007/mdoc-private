# e-Doc — Citizen Request Lifecycle & Delivery Flow (Design Doc)

**Project**: Electronic Document service for Tasseng offices (eDoc web + mDoc citizen mobile)
**Database**: `edoc_tasaeng` (MySQL, apis.com.la)
**Status**: LIVE prototype — intake, draft/save, submit, and certificate generation implemented
**Date**: 2026-08-17

---

## 1. Purpose

This document defines the **end-to-end lifecycle** of a citizen's document request —
from the moment a citizen asks for a paper/letter/certificate, through office processing,
fee payment, signing, up to **delivery of the finished document to the citizen**.
It is the reference for both the web staff app (eDoc, running now) and the citizen
mobile app (mDoc, to be developed later).

It also draws a **hard boundary** around the colleague's budget/accounting work (see §3)
so the two development tracks never overwrite each other.

---

## 2. Actors & Entry Channels

| Actor | Tool | Role |
|---|---|---|
| **Citizen** | mDoc mobile app (future) | Submits request from home; tracks status; pays by QR; downloads/collects document |
| **Citizen (walk-in)** | eDoc web at the Tasseng office (now) | Officer enters the request on the citizen's behalf |
| **Tasseng officer** | eDoc web | Creates/edit drafts, submits, reviews, records payment, prints/generates document, hands over |
| **Signatory / head** | eDoc web | Approves and signs the official document |
| **Admin / roll-up** | eDoc web | Master data (doc types/items/fields), reports per village→tasseng→district→province |

**Two entry paths, one pipeline** (both write to the same `edoc_document` table):

```
Path A (mobile, later)   Citizen ──mDoc app──▶ edoc_document (SUBMITTED)
Path B (walk-in, now)    Citizen at office ──officer enters via eDoc──▶ edoc_document (DRAFT → SUBMITTED)
```

---

## 3. SCOPE BOUNDARY (IMPORTANT — do not cross)

| Area | Owner | Our rule |
|---|---|---|
| Budget planning, chart of accounts, journals, accounting, payments bookkeeping (`ap_`, `gen_`, `acc_`, `payment_` tables, budget menus) | **Colleague** (budget/accounting track) | **NEVER touch, edit, or overwrite.** Read-only references only (e.g. `ap_office` for `off_id`). |
| **Basic data** (geo hierarchy province/district/taseng/village, doc type/item catalog, lookup tables) | **Us** (eDoc track) | We own `edoc_*` master data. |
| **Document service** (request intake, workflow statuses, form data, certificate generation, delivery) | **Us** (eDoc track) | We own `edoc_document*`, `edoc_form_data`, `edoc_file_store`, etc. |
| Fee payment **booking to accounts** (once a fee is paid, mapping to revenue accounts) | **Us** writes the mapping records; **colleague's** accounting consumes them | We write `edoc_payment_acct_mapping`; we do NOT post into the colleague's journal. Hand-off point only. |

Rule of thumb: any table starting with `edoc_` is ours; anything else in this database is the colleague's. All our work lives under the `edoc_` prefix and the eDoc menus; we add menus under the existing boss sidebar, never replace his.

---

## 4. The Lifecycle (canonical flow)

```
┌───────────────────────────── CITIZEN REQUEST ─────────────────────────────┐
│  A. Mobile app (later)          B. Walk-in at Tasseng office (now)        │
│     citizen fills 2-tab form       officer opens New Request (ຄຳຮ້ອງໃໝ່) │
│     (person + document fields)     or ຄຳຮ້ອງໃໝ່ (ພົນລະເມືອງຂໍມາ)     │
└───────────────┬─────────────────────────────────┬─────────────────────────┘
                │                                 │
                ▼                                 ▼
          DRAFT (ຮ່າງ) ◀───────────────  officer saves draft,
          officer can reopen & edit         edit again any time
                │                                 │
                ▼                                 ▼
        ┌─────────────────────────────────────────────────┐
        │  SUBMITTED (ຮັບແລ້ວ) — request accepted,        │
        │  officer may print/verify; walk-in generates      │
        │  invoice (UNPAID) automatically                   │
        └───────────────────────┬─────────────────────────┘
                                ▼
        PENDING APPROVAL (ລໍຖ້າອະນຸມັດ)  — head reviews
                                │
                 ┌──────────────┴──────────────┐
                 ▼                             ▼
            APPROVED / ISSUED            REJECTED (ປະຕິເສດ)
            (ອະນຸມັດແລ້ວ)                reason recorded,
                 │                        citizen may re-apply
                 ▼
        PENDING PAYMENT (ລໍຖ້າຊໍາລະ) — fee from catalog
                 │        (mobile: QR; walk-in: cash at counter)
                 ▼
            PAID (ຊໍາລະແລ້ວ) — receipt issued;
                 payment mapping written for the
                 colleague's accounting (hand-off, no direct posting)
                 │
                 ▼
            SIGNED (ເຊັນແລ້ວ) — officer signs the printed document;
                 (status set via ເຊັນແລ້ວ button on review page, no data entry)
                 │
                 ▼
            HANDOVER (ສົ່ງໃຫ້ແລ້ວ) — document handed to citizen;
                 recipient name + national ID + date recorded in
                 edoc_document_handover (officer enters via ສົ່ງມອບ form on review page)
                 │
        ┌────────┴─────────┐
        ▼                  ▼
   DOWNLOADED         DELIVERED ON PAPER
   (ດາວໂຫລດແລ້ວ)        citizen collects the
   mobile user            printed/signed document
   downloads file         at the office
        │                  │
        └──────┬───────────┘
               ▼
        CLOSED (ສຳເລັດ) — any branch may also be
        CANCELED (ຍົກເລີກ) at any stage with reason

Status values (as implemented in eDoc UI):
ຮ່າງ / DRAFT · ຮັບແລ້ວ / SUBMITTED · ລໍຖ້າອະນຸມັດ / PENDING_APPROVAL ·
ອະນຸມັດແລ້ວ / ISSUED · ລໍຖ້າຊໍາລະ / PENDING_PAYMENT · ຊໍາລະແລ້ວ / PAID ·
ເຊັນແລ້ວ / SIGNED · ສົ່ງໃຫ້ແລ້ວ / HANDOVER · ດາວໂຫລດແລ້ວ / DOWNLOADED ·
ປະຕິເສດ / REJECTED · ຍົກເລີກ / CANCELED
```

### 4.1 Status bookkeeping

Every transition is appended to `edoc_document_status`:
`doc_id`, `status`, `remark` (Lao, e.g. "ບັນທຶກເປັນຮ່າງ", "ສົ່ງຄຳຮ້ອງ"), `changed_by`, `created_at`.
This gives a full audit trail per document (proven live with doc 25: DRAFT → DRAFT → SUBMITTED).

### 4.2 The two submission modes on the web (today)

| Mode | Purpose | Result |
|---|---|---|
| `mode=draft` (New Request) | officer builds the request, can save as draft | DRAFT (editable later) or SUBMITTED |
| `mode=submit` (ພົນລະເມືອງຂໍມາ) | walk-in citizen present, direct submission | SUBMITTED + UNPAID invoice |

### 4.3 Person data & document fields

- **Tab 1 – Person**: reuse `edoc_citizen` keyed by National ID; lookup codes saved,
  Lao names shown (country, ethnicity, religion, occupation); 4-level geo cascade
  (province → district → taseng → village) for current address and birth place.
- **Tab 2 – Document**: fields rendered from `edoc_doc_field` for the chosen item;
  values stored in `edoc_form_data` (doc_id, field_id, value).
- Same 2-tab page is used for **add-new** and **edit draft** (identical dataset).

### 4.4 Delivery artifacts

- **Certificate generator** (implemented): fills the official `.docx` template
  (e.g. ໃບຢັ້ງຢືນທີ່ຢູ່) from document + citizen + residence data, resolving
  all codes back to Lao names. Generate button on the list for cert-type docs.
- **Future**: signed PDF export + `edoc_file_store` for mobile download
  (mDoc download step), and paper print for walk-in delivery.

---

## 5. Design Principles (prototype)

1. **One pipeline, two channels** — the mobile app (later) and the walk-in form (now)
   write identical records; the officer side is built first and reused.
2. **Codes in DB, names in UI** — every dropdown (geo, ethnicity, religion, occupation,
   nationality) stores a code and displays the Lao name. No free text where a code exists.
3. **Everything auditable** — every status change logged; citizen record keyed by NID.
4. **Additive only** — all `edoc_*` tables/menus added next to the boss's existing
   structure; the colleague's budget/accounting area is never modified.
5. **Prototype pragmatism** — plain PHP + PDO on the live server, verified by
   `php -l` + live probes; no test framework (per repo convention).
6. **Geo master data is the audit basis** — the taseng list page (province/district
   filters, names only) exists so the office can verify the completeness of the
   official taseng/village list against reality.

---

## 6. Implementation Status (2026-08-17)

### Done & live
- Geo hierarchy: 18 provinces / 148 districts / 1,408 tasengs / 8,828 villages
  (official Lao names + codes).
- Lookup tables: country (75), ethnicity (50), religion (8), occupation (21);
  citizen Tab-1 dropdowns store codes, show Lao names.
- 4-level geo cascade (current address + birth place) in both modes; village
  dropdown bug (tasseng_id vs taseng_id) fixed; birth cascade type-map bug fixed.
- Two-level doc chooser (type → item); `mode=draft` / `mode=submit`.
- 2-tab request page (person + document fields) used for **both add-new and edit-draft**;
  DRAFT edit prefills both tabs; save updates instead of inserting.
- Walk-in submission → SUBMITTED + UNPAID invoice; status history logged.
- Taseng list page: 2 cascading filters (province → district), Lao names only.
- Village list page: 3 cascading filters (province → district → taseng).
- Residence certificate generator (docx from template, code→name resolution).
- DataTables search fixed on list pages (missing CDN includes).

### Next (roadmap)
1. Officer review screen: approve / reject with reason (PENDING_APPROVAL → ISSUED | REJECTED).
2. Payment recording at counter (cash) + QR stub for mobile; receipt number; write
   `edoc_payment_acct_mapping` for the colleague's accounting (hand-off only).
3. Signing step + upload signed file to `edoc_file_store`.
4. Handover / delivery tracking (collection at office or download).
5. mDoc mobile app: registration/login, submit request, status tracking, QR payment, download.
6. Reports: village → tasseng → district → province roll-up (revenue & document stats).

---

## 7. Open Design Questions

1. Exact approval chain: does every document type need the same reviewer/signatory,
   or per-type signers (`edoc_document_signer` exists)?
2. Payment channels for the prototype: cash at counter now; QR via which bank/e-wallet
   (BCEL One?) later — API details pending.
3. Citizen identity verification level (ID card number only vs OCR scan).
4. Do walk-in citizens get a printed invoice/receipt at the counter?
5. mDoc registration: phone+password only, or NID-linked?

---

## 8. Related Files

- `1-edoc_design.md` — data schema design (tables, relationships, original status flow).
- `3-schema_edoc.sql` — full DDL for `edoc_*` tables.
- `4-php_app_structure.md` — PHP app structure & security baseline.
- `5-service_layer_plan.md` — shared service-layer architecture.
- `6-openapi-spec.yaml` — mDoc citizen API contract (mobile app will implement).
- `README.md` — project overview & status.
- This file — the lifecycle/flow definition.
