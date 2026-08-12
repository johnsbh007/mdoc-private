# E-Doc Component — Schema Design Documentation

**Project**  : Electronic document + local administration finance management (Tasseng)
**Database**  : `edoc_tasaeng` (MySQL, apis.com.la)
**Doc status**: Live — API deployed & verified (2026-08-11)

---

## 1. Background & Goal

The system is split into two modules (agreed with the boss):

- **Module 2 — Budget / Finance**: budget planning and accounting. The company already has a
  mature implementation. The existing tables in this database (`ap_`, `gen_`, `acc_`,
  `payment_` prefixes) are its engine (chart of accounts, budget plans, general journal,
  payment lines, office/user/menu permissions).
- **Module 1 — e-Doc (electronic document)**: citizen-facing document applications,
  approval/signing, QR-code fee payment, file download, plus roll-up reporting from
  Village → Tasseng → District → Province. **The database currently has no tables for this**
  — those are the ones to be added.

To keep the new part clearly separated from the existing budget tables, **all new e-Doc
tables use the `edoc_` prefix**.

### Naming convention

| Module             | Prefix                        | Examples                          |
|--------------------|-------------------------------|-----------------------------------|
| Budget/Finance (existing) | `ap_` `gen_` `acc_` `payment_` | `ap_budget`, `gen_jn`, `acc_code`, `payment_items` |
| e-Doc (new)        | `edoc_`                       | `edoc_doc_type`, `edoc_document`  |

### Existing tables reused / linked

The e-Doc tables link back into the budget module instead of duplicating data:

- `ap_office` (offices/units): every e-Doc record carries `off_id` so reporting is
  consistent on both the document side and the budget side.
- `acc_code` (chart of accounts): every document item / fee maps to a revenue account
  (`ac_code`). When a fee is paid, the amount is booked against the correct debit/credit
  accounts, so collections flow automatically into the budget module.

---

(Full DDL in `3-schema_edoc.sql`.)

## 2. Current Status (2026-08-07)

The `edoc_` schema has been **applied to the live `edoc_tasaeng` database** and the
reference/master data **seeded** from the source requirements document:

- 20 new `edoc_` tables created (ADDITIVE — existing budget tables untouched).
- Seeded: `edoc_doc_type` = 14 categories (**code 14 now deactivated**, see below),
  `edoc_doc_item` = 51 items with price/unit, `edoc_fee_type` = 4 types.
- Transaction/workflow/payment/file tables are empty by design (populated at runtime).
- **Category overlap resolved**: 14 (ຊື້-ຂາຍ) was a duplicate of 07 (ເອກະສານຊື້-ຂາຍ);
  5 sale items moved to 07, category 14 deactivated (`is_active=0`). See `1-edoc_design.md` §7.

---

## 3. Current Status (2026-08-11) — API live

The **mDoc Citizen API** is deployed on apis.com.la and verified with live requests:

- **Base URL**: `https://mdoc.apis.com.la/api/v1`
- **Deployment path**: `/var/www/html/mdoc/api/` (under the existing mdoc vhost)
### Verified working (via curl against production):
  - `GET /api/v1/doc-types` — 13 active categories, Lao UTF-8
  - `GET /api/v1/doc-items?doc_type_id=N` — catalog with prices
  - `GET /api/v1/hierarchy` — **18 provinces / 148 districts / 1,408 tasengs / 8,828 villages**
    (official Lao names+codes sourced from `tax_ets.provinces/districts/villages`, seeded 2026-08-12;
    taseng level derived from village-code groupings + `ncode` added to taseng/village as the new coding scheme)
  - `POST /api/v1/auth/register` — citizen registration (bcrypt + JWT)
  - `POST /api/v1/auth/login` — login, returns JWT
  - `GET /api/v1/auth/me` — Bearer-token protected profile
  - `GET /api/app/config.php` — **403 blocked** (credentials protected by `.htaccess` deny)
- **Still stubbed (return 501)**: document submission, listing, detail, QR payment,
  payment callback, download, receipt — pending workflow rules agreement.
- **Local mirror**: `D:\m-doc-taseng\api-deploy\` — keep in sync when iterating.

See `6-openapi-spec.yaml` (contract) and `5-service_layer_plan.md` (architecture).