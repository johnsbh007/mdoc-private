# Technical Stack Plan — e-Doc (Electronic Document) Module

**Database**: `edoc_tasaeng` (MySQL, apis.com.la)
**Status**: Agreed approach (2026-08-07); **updated 2026-08-11 — API now live**

---

## 1. Decision Overview

| Layer | Choice | Rationale |
|---|---|---|
| Database | **MySQL** | Reuse the SAME `edoc_tasaeng` database the existing budget module uses (shared `acc_code` revenue accounts + shared `off_id` offices). No dual-engine/bridge. PostgreSQL advantages not needed here. |
| Staff/admin web app (eDoc) | **PHP**, direct MySQL access | Fast to build, team can maintain (existing codebase is PHP), and NO middle-API layer required. |
| Citizen interaction (mDoc) | **PWA + JSON API (deployed 2026-08-11)** | Citizen PWA (mobile-friendly web app) consumes `https://mdoc.apis.com.la/api/v1`. The API runs on the same server as the DB (PHP, direct MySQL). QR payment works via web "show QR" → citizen scans with wallet/bank app. |
| Native mobile app (Flutter) | **Not in v1 — kept as PATH B (on standby)** | Build only if/ when the client explicitly requires an app-store-installable application. The deployed API already returns JSON, so a future Flutter app can consume it without rework. |

---

## 2. Two possible paths (decided: Path A now, Path B only on demand)

### PATH A — All PHP (chosen)
- One codebase. Citizen interface = mobile-optimized web app (responsive).
- No middle API anywhere — PHP connects to MySQL directly.
- Fastest to build, easiest for the whole team to continue.
- QR fee payment: system shows a QR code; citizen scans it with their e-wallet/banking app.

### PATH B — Flutter native mobile app (kept in reserve, not built)
- Trigger only if the client requires a native Android/iOS app in the app stores.
- Requires adding a PHP JSON API backend that the Flutter app calls (mobile apps cannot open direct DB connections).
- Implies maintaining 3 layers (PHP admin + PHP API + Flutter app) and depends on the single Flutter-capable developer.
- Decision rule: ask client first ("is a mobile website acceptable, or do you need an installed app?"). If website is acceptable → Path A. Only if native is mandatory → Path B.

---

## 3. Why "no middle API" applies to the web app but NOT to a native app

- **Web (PHP)**: the browser tab runs on a machine which can hold a session; PHP opens a MySQL connection server-side → no API layer needed.
- **Native app (phone)**: the app runs on the user's device and CANNOT open a direct DB connection over the internet. It must call an HTTP API. So "no middle API" is ONLY valid for the web-admin half, not for any native mobile app.

---

## 4. Delivered scope (v1) & supporting notes

- Staff app (eDoc): PHP + MySQL, direct connection. Covers master data, approvals/workflow, fee handling, QR show, reporting, and the Village→Tasseng→District→Province roll-up.
- Customer flow (mDoc): mobile-responsive PHP web app on the same codebase.
- Shared `edoc_tasaeng` DB links fees to existing `acc_code` revenue accounts so collections flow into the budget module automatically.

### Open / to confirm with client/boss
1. Is the customer interface expected as an installed native app, or is a mobile website acceptable? (Determines whether Path B ever activates.)
2. QR fee collection — payment channel (bank / e-wallet), and whether the QR is
   **receiver-presented** (system shows a QR, citizen scans with their wallet app — matches
   Path A web flow) or **payer-presented** (citizen app generates a QR the cashier scans).

---

## 5. Live Deployment (2026-08-11)

The **mDoc Citizen API** is deployed and verified on apis.com.la:

| Item | Value |
|------|-------|
| Base URL | `https://mdoc.apis.com.la/api/v1` |
| Server | apis.com.la (same host as MySQL — localhost connection, no public DB exposure) |
| Path | `/var/www/html/mdoc/api/` (under existing mdoc vhost, no new vhost/cert) |
| Stack | Plain PHP 8.3 + PDO, no framework, no Composer |
| Auth | bcrypt (`password_hash`) + minimal HS256 JWT (24h expiry) |
| Config protection | `app/.htaccess` denies direct access (credentials never served) |
| CORS | Locked to `https://mdoc.apis.com.la` |

**Verified endpoints** (curl, production): `doc-types`, `doc-items`, `doc-items/{id}`,
`doc-items/{id}/fields`, `hierarchy`, `auth/register`, `auth/login`, `auth/me`.

**Not yet implemented** (stub → 501): `documents` (submit/list/detail), `pay-qr`,
`payments/callback`, `download`, `receipt` — pending workflow-rules agreement with the
edoc admin app side.

**Local mirror**: `D:\m-doc-taseng\api-deploy\` (outside server) — keep in sync.

## 6. Index of Files
- `./edoc_design.md` — module data design + workflow.
- `./schema_edoc.sql` — DDL for the `edoc_` tables.
- `./README.md` — overview & current status.
- This file — technical stack plan.