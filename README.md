# eDoc / mDoc — ລະບົບຄຸ້ມຄອງຖານຂໍ້ມູນເອກະສານ (Document Management System)

Citizen-facing document service + internal admin web app for Taseng-level
government offices (ຕາແສງ). Built for **apis.com.la**.

## Architecture (agreed approach)

- **Database:** single shared MySQL database `edoc_tasaeng` on apis.com.la.
- **Web admin app:** lives at `/var/www/html/edoc` (https://edoc.apis.com.la).
  Basic Data (ຂໍ້ມູນພື້ນຖານ) screens use **direct PDO** to MySQL
  (Path 1 — same style as the boss's app and tax_ets). No API layer for CRUD
  on lookup tables.
- **REST API:** lives at `/var/www/html/mdoc/api` (https://mdoc.apis.com.la/api/v1).
  Used for the **Document Service** (ບໍລິການເອກະສານ) flow and any future
  mobile/PWA client. Single business "brain" in `DocumentService.php` (JWT auth).
- **PWA:** Vue 3 + Vite app (intended future mobile client) under `mdoc-pwa/`.

## Repository layout

```
api-deploy/
  app/            REST API (Router, Request, Response, DocumentService)
                  config.php is gitignored — use config.php.example
  web_pages/      PHP CRUD pages for Basic Data (pages/, modal/, insert/,
                  ajax/search/, ajax/del/) — deploy to /var/www/html/edoc
docs/             Design notes, schema (3-schema_edoc.sql), SQL seed/sync
                  scripts, official hierarchy JSON
scripts/          Python helpers to rebuild/seed the hierarchy
api-scaffold/     Earlier API prototype (reference)
mdoc-pwa/         Vue 3 PWA front-end source (src/)
source/           Original .docx document templates (kept local, gitignored)
```

## Basic Data menu (ຂໍ້ມູນພື້ນຖານ) — implemented

| Menu | Table |
|------|-------|
| ລາຍຊື່ແຂວງ   | edoc_province   |
| ລາຍຊື່ເມືອງ | edoc_district   |
| ລາຍຊື່ຕາແສງ | edoc_taseng     |
| ລາຍຊື່ບ້ານ   | edoc_village    |
| ປະເພດເອກະສານ | edoc_doc_type   |
| ລາຍການເອກະສານ | edoc_doc_item   |
| ປະເພດລາຍຮັບ | edoc_fee_type  (Article 17) |
| ປະເພດລາຍຈ່າຍ | edoc_expense_type (Article 18) |

Seeded: 18 provinces, 148 districts, 1,408 tasengs, 8,828 villages
(officially synced from `tax_ets`), plus 6 income + 4 expense categories.

## Deploy notes

- Web pages deploy to `/var/www/html/edoc` (match the boss's app conventions:
  `session_name('ACCOUNT_SATHA_SESS')`, `pages/login.php` auth).
- API deploys to `/var/www/html/mdoc/api`; copy `config.php.example` → `config.php`
  and set a 64-char random JWT secret.

## CI

GitHub Actions (`.github/workflows/ci.yml`) runs on every push/PR to `main`/`develop`:
- **PHP Syntax Lint** — `php -l` on every `.php` file (catches syntax errors before deploy).
- **PWA Build Check** — `npm ci` + `npm run build` for `mdoc-pwa/` (non-blocking).

## Security

Credentials and `config.php` are gitignored. Never commit DB passwords or JWT secrets.
