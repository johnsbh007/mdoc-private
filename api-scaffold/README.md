# API Scaffold for mDoc Citizen PWA

This scaffold provides a minimal, framework-agnostic way to add a JSON API to an existing PHP project that follows the service layer pattern.

## What it solves

- Gives your mDoc PWA a clean API contract (`/api/v1/*`) to consume
- Ensures both the Admin UI (HTML) and the PWA (JSON) use the **exact same business logic** (via `DocumentService`)
- Avoids the shared-database anti-pattern where two codebases drift apart
- Is **not** a separate deploy—it lives inside your existing PHP codebase

## Folder structure to add to your project

```
your-project/
├── public/
│   └── api.php              # <-- Front controller (from this scaffold)
├── app/
│   ├── Api/
│   │   ├── Router.php       # <-- Simple HTTP method + path dispatcher
│   │   ├── Request.php      # <-- Helpers for JSON body, query, headers
│   │   └── Response.php     # <-- Helpers for json_response(), file download
│   └── Services/
│       └── DocumentService.php  # <-- Contains ALL business logic (stubbed)
├── bootstrap.php            # <-- Initializes PDO singleton, helpers
�└── config.php.example       # <-- Copy to config.php and fill in DB creds
```

## Integration steps for your existing project

1. **Create the folders** and copy the files from this scaffold into your project root as shown above.
2. **Copy `config.php.example` to `config.php`** and fill in your database credentials.
3. **Ensure your project loads `bootstrap.php` early**:
   - In your existing `public/index.php` (or front controller), add:
     ```php
     require __DIR__ . '/../bootstrap.php';
     ```
   - This ensures the PDO singleton (`db()` helper) is available.
4. **Implement the business logic** in `app/Services/DocumentService.php`:
   - Replace all `throw new Exception('Not implemented');` stubs with real code.
   - Use the `db()` helper (or `DB::conn()` if you prefer) for database access.
   - All validation, workflow, invoicing, QR generation, payment callback handling, and accounting mapping goes here.
5. **Define your API routes** in `public/api.php`:
   - The scaffold already shows a basic router. You will add routes like:
     ```php
     $router->get('doc-types', [DocumentService::class, 'getAllDocTypes']);
     $router->post('documents', [DocumentService::class, 'submitApplication']);
     $router->get('documents/{id}/pay-qr', [DocumentService::class, 'generatePaymentQr']);
     $router->post('payments/callback', [DocumentService::class, 'processPaymentCallback']);
     ```
6. **Secure the endpoints**:
   - For citizen endpoints (`/auth/*`, `/documents/*`, etc.), validate the JWT/session in a middleware or at the start of each method.
   - For `/payments/callback`, verify the request comes from a trusted bank IP and validate the HMAC signature (do this in the controller before calling the service).

## Why this works

- The **Admin UI** (your colleague's PHP/HTML) continues to work exactly as before—but now their controllers call `DocumentService` methods instead of writing raw SQL.
- The **Citizen PWA** (your Vue/React app) calls `/api/v1/*` endpoints, which also call the **same** `DocumentService` methods.
- There is **only one source of truth** for business logic. No drift, no duplicate validation, no payment callback races.

## Validation

You can test the API with curl or Postman once the routes are implemented:
```bash
# Example: Get document types
curl -s https://your-domain.com/api/v1/doc-types | jq

# Example: Login (returns JWT)
curl -s -X POST https://your-domain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"phone":"02055551234","password":"secret123"}' | jq
```

## Next steps

1. Present `docs/6-openapi-spec.yaml` to your colleague—it defines the exact JSON contract.
2. Use this scaffold to give them a starting point for the API layer.
3. Build your mDoc PWA against the mock API (or the real one once they implement the service methods).

**This approach keeps the project as a single deployable PHP application while giving you the clean API separation you need.**