# PHP Application Structure — e-Doc (Module 1)

**Stack**: PHP + MySQL (Path A, from `tech_stack.md`)
**Database**: `edoc_tasaeng` (apis.com.la)
**Goal**: Lightweight, team-maintainable, no framework dependency, direct MySQL access,
no middle-API. Extensible later to add a JSON endpoint if Path B ever activates.

---

## 1. Principles

- **Simple & boring PHP** (no heavy framework) so any PHP dev on the team can continue it.
  (Matches how the existing budget module is likely written — plain PHP + MySQL.)
- **Front controller + lightweight router** in `.htaccess`/`index.php` — one entry point.
- **Database via PDO + prepared statements** (never raw string-concatenated SQL) for security.
- **Lao UI**: all screens render Lao text (as the project requires).
- **Sessions for auth** on the staff side; basic token/session auth on the citizen side.
- Everything below a `web/` (or `public/`) document root; app logic above it (security).

---

## 2. Recommended folder layout

```
e-doc/                        # project root (repo)
├── public/                   # DOCUMENT ROOT (only this is served by Apache/Nginx)
│   ├── index.php             # front controller / router entry
│   ├── .htaccess             # rewrite all request -> index.php
│   ├── css/  js/  img/       # static assets
│   └── uploads/              # citizen-uploaded / issued files (signed PDFs) - not web-executable
├── app/
│   ├── Core/
│   │   ├── Router.php        # parse URL -> controller/method
│   │   ├── DB.php            # PDO singleton (reads credentials from config)
│   │   ├── Auth.php          # session-based auth (staff + citizen)
│   │   ├── Response.php      # render view / JSON / redirect helpers
│   │   ├── Csrf.php          # CSRF token helper
│   │   └── Validator.php     # input validation
│   ├── Controllers/
│   │   ├── Staff/            # admin-side controllers
│   │   ├── Portal/           # citizen-side controllers
│   │   └── Api.php           # (reserved for Path B if ever needed)
│   ├── Models/               # one class per edoc_ table / business object
│   ├── Views/
│   │   ├── staff/            # admin templates (master data, workflow, reports)
│   │   ├── portal/           # citizen templates (login, apply, pay QR, status, download)
│   │   └── layouts/          # shared header/footer
│   ├── Middleware/
│   │   ├── AuthGuard.php     # require login
│   │   ├── Csrf.php
│   │   └── Cors.php          # (only if a web/JSON endpoint is added)
│   └── Helpers/
│       ├── Qr.php           # generate QR for fee payment
│       ├── DocRenderer.php  # build the official document (PDF/print view)
│       └── Report.php       # roll-up aggregations (village->tasseng->district->province)
├── config/
│   ├── config.php           # env, DB creds, payment channel, upload dirs
│   └── routes.php            # URL -> controller mapping
├── storage/
│   ├── logs/
│   └── sessions/
├── vendor/                    # if we ever add composer libs (QR gen, PDF, etc.)
├── tests/                     # basic smoke tests
└── README.md                  # how to run/deploy
```

---

## 3. Request → Response flow

```
Browser → public/.htaccess → public/index.php → Router
   → match URL to Staff|Portal controller (+ optional middleware Auth/Role)
   → controller loads Models (PDO queries)
   → controller calls Response (render view / JSON / redirect)
   → view HTML output (local language)
```

### Router mapping example (`config/routes.php`)
```
# staff
/staff/login                 GET  -> Staff\LoginController@showLogin
/staff/login                 POST -> Staff\LoginController@login
/staff/dashboard             -> Staff\DashboardController@index
/staff/doctype                -> Staff\MasterDataController@docType
/staff/docitem                -> Staff\MasterDataController@docItem
/staff/office/hierarchy       -> Staff\AdminController@hierarchy  (province/district/tasseng/village)
/staff/docs/pending           -> Staff\WorkflowController@pending
/staff/docs/{id}/approve      -> Staff\WorkflowController@approve
/staff/fees/collect           -> Staff\FeesController@collect
/staff/reports/rollup         -> Staff\ReportsController@rollup

# citizen (mobile-responsive web)
GET /                            -> Portal\HomeController@index
GET /register                    -> Portal\AuthController@register show
POST /register                   -> Portal\AuthController@registerSubmit
GET /login                       -> Portal\AuthController@login
POST /login                      -> Portal\AuthController@loginSubmit
GET /docs/{category}             -> Portal\DocCatalogController@list
GET /docs/new/{item}            -> Portal\DocController@new  (fill fields)
POST /docs/{doc}/submit          -> Portal\DocController@submit
GET /docs/{doc}/status           -> Portal\DocController@status
GET /docs/{doc}/pay-qr           -> Portal\PayController@showQr
POST /docs/{doc}/pay/callback    -> Portal\PayController@paymentCallback  (from bank/e-wallet)
GET /docs/{doc}/download         -> Portal\DownloadController@download (trigger built PDF)
GET /docs/{doc}/receipt          -> Portal\ReceiptController@show

# (reserved Path B) /api/...  -> Api.php
```

---

## 4. Database access (PDO)

Connection is centralized; all SQL uses prepared statements.

```php
// app/Core/DB.php (simplified)
final class DB {
  private static ?PDO $pdo = null;
  public static function conn(): PDO {
    if (self::$pdo === null) {
      $c = require __DIR__.'/../../config/config.php';
      self::$pdo = new PDO(
        "mysql:host={$c['db']['host']};dbname={$c['db']['name']};charset=utf8mb4",
        $c['db']['user'], $c['db']['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
      );
    }
    return self::$pdo;
  }
}
```

Usage example (model `DocItem`):
```php
$stmt = DB::conn()->prepare(
  "SELECT i.*, t.name_lo AS type_name
     FROM edoc_doc_item i
     JOIN edoc_doc_type t ON t.id = i.doc_type_id
    WHERE :kw = '' OR i.name_lo LIKE :term
    ORDER BY i.doc_item_code"
);
$stmt->execute(['kw'=>1,'term'=>'%'.$q.'%']);
$items = $stmt->fetchAll();
```

**Rule**: never concatenate user input into SQL; always use `:param` placeholders.

---

## 5. Auth / roles

| Interface | Table | Session | Login fields |
|---|---|---|---|
| Staff | `ap_users` | PHP session (`$_SESSION['ap_user_id']`) | username + password_hash (reuse budget) |
| Citizen | `edoc_citizen` | PHP session + remember token | phone OR email + password_hash |

- Passwords stored as `password_hash()` (bcrypt) — never plaintext.
- CSRF token on all POST forms via `Csrf::token()`.
- Site rows: same `ap_users` reused, or separate `edoc_user` if needed (see `edoc_design.md` §6).
- ToDo: link citizen `edoc_citizen` session to `edoc_document`/`edoc_invoice` so downloads are scoped to the owner.

---

## 6. Key integration points (no middle API)

- Fee payment: `Portal\PayController` generates a QR (via `QrCode` helper + `edoc_payment.qr_token`),
  then on callback/confirmation writes `edoc_payment` + `edoc_payment_acct_mapping` and posts
  the revenue line into the budget side (`acc_code`) — same DB, same server.
- Report roll-up: `Report.php` builds Village→Tasseng→District→Province via the `edoc_village`
  hierarchy + joins to `edoc_document`/`edoc_invoice`.

---

## 7. Security hardening baseline (non-negotiable)

Plain PHP is not inherently insecure — insecure *code* is. A framework like Laravel is safer
because it forces good defaults. We keep the light stack but **bake in the Laravel-equivalent
controls as architecture**, so the team gets the same protections without the framework.

### 7.1 Non-negotiable rules (all code must follow)

| # | Control | Implementation |
|---|---|---|
| 1 | **SQL injection** | PDO + prepared statements ONLY. Never concatenate user input into SQL. All queries go through Models, never inline in views. |
| 2 | **XSS** | Every output through `e()` helper (= `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`). Views never echo raw `$_GET/$_POST` or DB strings directly. |
| 3 | **CSRF** | Token on every POST form; checked by middleware. `Csrf::token()` in form, `Csrf::verify()` before any state change. |
| 4 | **Passwords** | `password_hash()` (bcrypt) for storage; `password_verify()` on login. Never plaintext, never md5/sha1. |
| 5 | **Auth/session** | HttpOnly + SameSite=Lax cookies; `session_regenerate_id()` after login; `Secure` flag on prod (HTTPS). Session timeout. |
| 6 | **Brute force** | Login throttling: max N attempts per user/IP per time window (e.g. 5 tries / 5 min), small sleep on failure. |
| 7 | **IDOR** | Every `edoc_document`/`edoc_invoice`/`edoc_file_store` read MUST check the session owner (`WHERE id=? AND citizen_id=?`). Users may only see their own records. |
| 8 | **Uploads** | Validate MIME + extension + size; randomize stored names; store outside web root or in a dir with PHP execution disabled; never trust client filename. |
| 9 | **Config secrecy** | DB creds only in `config/config.php` (outside `public/`); never in repo (use env or .gitignore); `display_errors=Off` in prod. |
| 10 | **Errors/logs** | Central log to `storage/logs/`; generic user-facing errors (never dump SQL/trace); DO NOT log passwords, tokens, or full payment refs. |
| 11 | **Headers** | Default security headers via middleware: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, CSP. |
| 12 | **HTTPS** | Force HTTPS in prod (redirect); HSTS header. |

### 7.2 Helpers to add in `app/Core/` (from first commit)

- `E.php` — `e($v)` output escaping helper.
- `Csrf.php` — token gen/verify (already in layout).
- `Auth.php` — session auth + `requireStaff()`, `requireCitizen()` guards (already in layout).
- `Throttle.php` — simple login rate limiter (DB or APCu-backed counter).
- `SecurityHeaders.php` — emit headers in middleware.
- `Validator.php` — central input validation (already in layout).

### 7.3 Password policy

- Min 8 chars, bcrypt cost default (10-12).
- No forced expiry for internal staff (keeps it simple), but passwords must be unique per user.
- Citizens: phone/email + password; same hashing rules.

### 7.4 Payment endpoint security (extra care)

- QR payment callback from the bank/e-wallet MUST be verified server-side (signature/HMAC,
  IP whitelist where possible) — never trust a GET/POST param alone to mark `edoc_payment` PAID.
- Payment status transitions only forward (PENDING → PAID), never backward.
- The `edoc_payment_acct_mapping` write to budget accounts is server-generated from
  `edoc_doc_item.ac_code` + payment, never client-supplied.
- Amount shown vs amount paid must match exactly; log any mismatch for audit.

### 7.5 Operational hardening

- Keep PHP + MySQL patched; use a recent PHP 8.x.
- If Composer libs are added (QR/PDF), run `composer audit` in CI and pin versions.
- Backup DB + `uploads/` (existing backup routine) and test restore.
- Log a summary of auth failures + payment anomalies; review weekly.
- Optional later: OWASP ZAP / basic scanner in CI before releases.

### 7.6 If security budget/team maturity grows → framework pivot

Documented decision point in `2-tech_stack.md`. If the team later prefers "the framework
just does it", switching to Laravel is a clean pivot — the app structure maps 1:1
(Router→routes, Models→Eloquent, Views→Blade with auto-escaping, Middleware→Laravel middleware).

---

## 8. Current scope note

The structure is the v1 plan. If Path B (native Flutter app) is ever activated, add the JSON
endpoint layer under `app/Controllers/Api.php` + `Middleware/Cors.php`; the rest of the app
stays the same.

---

## 9. Files in this docs folder

- `1-edoc_design.md` — module data design + workflow.
- `2-tech_stack.md` — stack decisions.
- `3-schema_edoc.sql` — DDL.
- `4-php_app_structure.md` — PHP app structure + security baseline (this file).
- `README.md` — overview/status.