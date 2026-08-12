# mDoc PWA (citizen front-end)

Mobile-first Progressive Web App for citizens to apply for official documents,
track status, pay fees by QR code, and download signed documents.

## Stack

- Vue 3 (Composition API) + Vite
- Pinia (state) + vue-router (navigation)
- Tailwind CSS v4 (@tailwindcss/vite)
- vite-plugin-pwa (Workbox: manifest, service worker, offline caching)

## Commands

```bash
npm install
npm run dev        # dev server on :5173 (PWA enabled in dev too)
npm run build      # production build → dist/ (includes SW + manifest)
npm run preview    # serve the production build locally
```

## API base URL

Defaults to `https://mdoc.apis.com.la/api/v1` (production).

Override for local dev against a different backend:

```bash
MDOC_API_BASE=http://localhost:8000/api/v1 npm run dev
```

## Structure

```
src/
├── api/client.js        # fetch wrapper: token injection, error normalization
├── stores/auth.js       # Pinia auth store (token + profile, localStorage)
├── router/index.js      # routes + auth guards
├── components/          # AppShell (bottom nav), StatusBadge, ErrorBanner, ...
├── views/               # Login, Register, Home, Catalog, DocItem, Apply,
│                        # MyDocs, DocDetail, Profile
└── styles/main.css      # Tailwind entry + Lao font stack
```

## PWA notes

- `registerType: 'autoUpdate'` — new builds auto-update the service worker.
- Catalog/hierarchy API responses are cached (NetworkFirst, 2h); auth/document
  endpoints are NetworkOnly (never cached).
- Status polling: DocDetail refreshes every 15 s while open.
- Offline: app shell + catalog are usable offline; mutations queue to a later
  iteration (Background Sync) once the document workflow endpoints go live.

## Backend contract

See `../docs/6-openapi-spec.yaml`. Implemented now: auth, catalog, hierarchy.
Workflow/payment endpoints return 501 until the admin-side rules are agreed.

## Deployment

Build → copy `dist/` to the server (e.g. `/var/www/html/mdoc/`). The API lives
at `https://mdoc.apis.com.la/api/v1` on the same host.
