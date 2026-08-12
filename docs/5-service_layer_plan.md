# Service Layer Plan — e-Doc (Module 1)

**Goal**: Implement a shared "Service Layer" for all business logic (Workflow, Validation, Accounting Mapping, Pricing). 
**Why**: Avoid the shared-DB anti-pattern where two apps (Admin PHP + PWA API) contain divergent, inconsistent business logic.
**Architecture**: 
- Admin UI (PHP) calls `Service` → returns HTML.
- Citizen PWA (API) calls `Service` → returns JSON.
**Status**: **Service layer deployed live on apis.com.la (2026-08-11)** — catalog + hierarchy +
auth implemented; workflow/payment endpoints stubbed pending rules agreement.

---

## 1. Service Layer Interfaces (`app/Services/DocumentService.php`)

All operations MUST pass through these methods to ensure data integrity.

| Method | Purpose | Input | Output |
|---|---|---|---|
| `submit(int $citizen_id, int $doc_item_id, array $fields)` | Create app | Citizen data | `Document` object |
| `approve(int $doc_id, int $user_id, string $remark)` | Workflow | Approver info | `Document` status |
| `generateQr(int $doc_id, string $method)` | Payment init | Invoice/QR logic | QR token + URL |
| `processCallback(array $payload)` | **Critical** | Bank JSON | Status, Accounting records |
| `getRollup(array $filters)` | Reporting | Levels/Dates | Aggregated data |
| `getHierarchy()` | Admin hierarchy | None | Provinces, Districts, Tassengs, Villages |

---

## 2. Why "Communication via DB" fails

If you bypass the service layer and both apps write directly to `edoc_` tables:

1. **Workflow Drift**: Admin panel logic and PWA logic will eventually diverge on state transitions (e.g., PWA allows submission that Admin logic rejects).
2. **Accounting Inconsistency**: `edoc_payment_acct_mapping` (linked to `acc_code`) is the heart of the finance reporting. If two codebases generate the mapping logic (debit/credit calculation), the financial reports will be wrong.
3. **Race Conditions**: Two apps handling the same status change for the same document ID = database lock conflicts or silent overrides.
4. **Integration Fragility**: Every schema change requires updates to TWO independent codebases instead of one shared service.

---

## 3. Implementation strategy

1. **Refactor**: Colleague moves existing business logic out of HTML controllers into `app/Services/DocumentService.php`.
2. **API Routes**: Colleague adds `public/api.php` as a light front-controller for JSON (using the same `DocumentService`).
3. **Consumption**: Your PWA calls these endpoints (`/api/v1/...`).

---

## 4. Payment Scenario (Example)

- **Admin Side**: Admin controller calls `DocumentService::generateQr($id, 'QR')`.
- **Citizen Side**: PWA calls `/api/v1/documents/{id}/pay-qr`. 
- **PHP API**: `ApiRouter` calls `DocumentService::generateQr($id, 'QR')`.

**Result**: Exact same logic used for both, centralized in one file. No logic in the PWA.

---

## 5. Next Steps
- ~~Agree on API JSON contracts (OpenAPI/Swagger)~~ ✅ done → `6-openapi-spec.yaml`.
- ~~Expose via `ApiRouter` (JSON)~~ ✅ done — deployed at `https://mdoc.apis.com.la/api/v1`
  (`/var/www/html/mdoc/api/`), local mirror `D:\m-doc-taseng\api-deploy\`.
- ~~Catalog + hierarchy + auth in `DocumentService`~~ ✅ done (doc-types, doc-items, fields,
  hierarchy, register/login/me verified live).
- **Remaining**: implement workflow/payment endpoints in `DocumentService`
  (`submitApplication`, `listMyDocuments`, `getDocumentDetail`, `getPaymentQr`,
  `processPaymentCallback`, `downloadDocument`, `getReceipt`) — depends on workflow rules
  agreed with the edoc admin app side.
- **Administrative Hierarchy**: The `getHierarchy()` method will provide the Province/District/Tasseng/Village data for PWA dropdowns. The admin UI should include a master data screen to populate these tables once the official Taseng list is available.