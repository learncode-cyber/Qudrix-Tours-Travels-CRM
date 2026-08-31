# Phase 2 Report — Complete CRM

**Master Directive numbering.** Older docs in this folder
(`PHASE_2_STATUS.md`, `PHASE_2_HANDOVER.md`, `PHASE_2_DELIVERABLES.txt`,
`PHASE_2_CRM_COMPLETION_STATUS.md`) use unrelated, earlier "Phase 2"
numbering from prior development cycles (webhooks/security, then a
separate CRM-completion pass). This report is the one the current
Master Development Directive asks for — Phase 2 = "Complete CRM."

**Date:** 2026-08-31 · **Branch:** `claude/qudrix-travel-crm-master-0opkmx`

---

## 1. What was implemented

An audit against the directive's Phase 2 checklist found most CRM
building blocks already existed from prior sessions (customer CRUD,
notes, tags, custom fields, tasks, lead pipeline, sales activity write
path). The real gaps closed this phase:

- **Deal management** — a genuine `Deal` entity (title, amount,
  currency, stage, probability, expected close date, owner), distinct
  from the pre-existing `DealStage`/`Lead` status history. Full CRUD, a
  dedicated stage-change endpoint that preserves stage-transition
  history, and a Kanban-style pipeline endpoint.
- **Customer 360 profile** — one endpoint aggregating a customer's
  leads, deals, bookings, quotations, communications, notes, tags, and
  timeline.
- **CRM Dashboard** — KPI endpoint (total leads, new leads this month,
  conversion rate, pipeline value by stage, deals won/lost, tasks due
  today, upcoming follow-ups).
- **Conversion funnel** — lead counts per funnel stage + overall
  conversion rate.
- **Follow-up calendar** — merges reminders, lead follow-up dates, and
  task due dates into one date-ranged feed.
- **Sales activity history (read path)** — the write path already
  existed; there was no way to list it back.
- **Lead CRUD completed** — `update`/`destroy` were missing entirely.
- **The project's first frontend** — a React + TypeScript admin UI
  (`/frontend`) covering this phase's CRM surface: login, dashboard,
  customers (list + 360 detail), leads (list + kanban pipeline), deals
  (list + kanban pipeline), tasks.

## 2. Files / modules changed

Backend (`PROJECT/`):
- New: `app/Models/Deal.php`, `app/Models/DealStageTransition.php`,
  `app/Http/Controllers/DealController.php`,
  `app/Http/Controllers/CrmDashboardController.php`,
  `database/migrations/2026_08_31_000000_create_phase2_crm_deals_table.php`,
  `tests/Feature/Phase2CrmTest.php`
- Modified: `app/Models/Customer.php` (added `quotations()`, `deals()`
  relations), `app/Models/Lead.php` (added `customer_id` to
  `$fillable` — see Bugs below — and `customer()` relation),
  `app/Http/Controllers/CustomerController.php` (added `profile360`),
  `app/Http/Controllers/LeadController.php` (added `update`/`destroy`),
  `app/Http/Controllers/PipelineController.php` (added
  `salesActivityHistory`), `routes/api.php`, `.env.example` (documented
  `CORS_ALLOWED_ORIGINS` — see Bugs below)

Frontend (new, `/frontend`): full Vite + React 18 + TypeScript project.
See `frontend/README.md` for its own file layout.

## 3. Database changes

New tables (migration `2026_08_31_000000_create_phase2_crm_deals_table.php`):
- `deals` — tenant-scoped, soft-deletable, FKs to `customers`, `leads`,
  `users` (owner), indexed on `(tenant_id, stage)` and
  `(tenant_id, owner_id)`.
- `deal_stage_transitions` — stage-history log for `deals`, mirroring
  the existing `deal_stages` table's pattern for `leads` (kept
  separate and unrenamed — see §12).

No existing table was altered.

## 4. API changes

See `DOCUMENTATION/API_DOCUMENTATION_COMPLETE.md`'s new "MASTER
DIRECTIVE PHASE 2 ADDENDUM" section for the full list with request/
response shapes. Summary:

- `GET/PUT/DELETE /api/v1/leads/{id}` (update/destroy added)
- `GET/POST/PUT/DELETE /api/v1/deals[/{id}]`, `PUT /api/v1/deals/{id}/stage`,
  `GET /api/v1/deals/pipeline`
- `GET /api/v1/customers/{id}/360`
- `GET /api/v1/crm/dashboard`, `GET /api/v1/crm/conversion-funnel`,
  `GET /api/v1/crm/follow-ups/calendar`
- `GET /api/v1/pipeline/sales-activities`

## 5. Frontend changes

First frontend in the repository. Pages: `/login`, `/dashboard`,
`/customers`, `/customers/:id` (360 view), `/leads` (list + kanban),
`/deals` (list + kanban), `/tasks`. JWT stored in `localStorage`,
attached via an axios request interceptor; a response interceptor
clears it and redirects to `/login` on 401. All data comes from the
real API — no hardcoded/sample data anywhere in the UI. Where a new
backend endpoint might not be live, pages fall back gracefully (e.g.
grouping `/deals` client-side if `/deals/pipeline` 404s) rather than
crashing or fabricating data.

## 6 & 7. Tests performed and results

- `php artisan test` (full suite, includes the new
  `tests/Feature/Phase2CrmTest.php`): **97/97 passing** (up from 89
  before this phase; +8 new tests covering lead update/delete, deal
  CRUD + stage transitions + the "can't set stage via general update"
  guard, customer 360 aggregation, CRM dashboard, conversion funnel,
  follow-up calendar, sales activity history).
- `npm run build`, `npm run typecheck` (`tsc -b --noEmit`), `npm run
  lint` (ESLint) in `/frontend`: all three **clean** (lint: 0 errors,
  1 pre-existing-pattern warning about React Fast Refresh, not a bug).
- **Live end-to-end verification**, not just unit tests: ran the actual
  Laravel dev server + built frontend together (`vite preview`) behind
  a real headless Chromium session (Playwright), logged in with a real
  seeded admin account, and clicked through every page
  (dashboard → customers → customer detail → leads → deals → tasks),
  asserting zero uncaught JS errors and inspecting the rendered DOM
  text for real created data (a live-created customer, deal, and
  their correct dollar amounts actually appearing on screen). This is
  what caught the three frontend/backend contract-mismatch bugs below
  — none of them would have been caught by `npm run build` or
  `tsc --noEmit` alone, since they were runtime data-shape mismatches,
  not type errors (the frontend's TS types were written defensively
  before the real backend shapes existed).

## 8 & 9. Bugs discovered and fixed

1. **`Lead::$fillable` was missing `customer_id`.** The column has
   existed since Phase 0/1 with a migration comment stating its exact
   purpose ("so a customer's originating leads can be found without
   guessing"), but was never added to the model's mass-assignment
   allowlist — every `Lead::create()` silently dropped it. Found while
   writing the Customer 360 test (a linked lead wasn't showing up).
   Fixed: added to `$fillable`, added the missing inverse `customer()`
   relation.
2. **CORS blocked the frontend from reaching the backend entirely.**
   `config/cors.php`'s `allowed_origins` defaults to empty (correct,
   secure-by-default), but `.env`/`.env.example` shipped it empty with
   no guidance — so the first real frontend request in this project's
   history failed outright with a CORS preflight error. Not a code bug
   (secure defaults are correct), but a documentation gap that made the
   app unusable out of the box. Fixed: added explanatory comments and
   examples to `.env.example`; set a working local value in this
   session's own `.env` for verification.
3. **Three frontend/backend response-shape mismatches**, all the same
   root cause: several new endpoints wrap their payload as
   `{"data": {...}}` (matching every other endpoint in this API), but
   the frontend — written defensively before these endpoints were
   confirmed live — typed three of them (`/deals/pipeline` via the
   leads-pipeline pattern, `/customers/{id}/360`, `/crm/dashboard`) as
   if the JSON body *was* the inner payload directly, one nesting level
   too shallow. Result: `/deals` and `/leads` pipeline pages crashed
   with "r is not iterable" / "r.map is not a function", the customer
   360 page crashed with "Cannot read properties of undefined (reading
   'name')", and the dashboard silently rendered placeholder dashes
   and $0 instead of real numbers. Caught only by the live-browser
   verification described above. Fixed all three: corrected the axios
   response types to the real `{data: T}` wrapper, fixed the two call
   sites that read the wrong nesting level, and rewrote the pipeline
   "normalize" helpers in `LeadsPage.tsx`/`DealsPage.tsx` to handle the
   real object-keyed-by-stage shape (`{new: {...}, qualified: {...}}`)
   instead of assuming an array.

## 10. Regression results

Full backend suite re-run after every fix: **97/97 passing**, no
regressions in any of the pre-existing 89 tests from before this phase
(auth, RBAC-adjacent middleware, bookings, quotations, analytics,
webhooks, PWA/offline sync, load/security tests, etc.).

## 11. Security considerations

- All new endpoints sit behind the existing `app.jwt` + `tenant` +
  `audit` middleware group — same tenant-scoping and audit-logging as
  every other protected route; every query explicitly filters by
  `tenant_id` (this app does not use global Eloquent scopes, by design
  — see prior phase reports for why).
- `PUT /api/v1/deals/{id}` explicitly rejects a `stage` field (422) to
  force stage changes through the dedicated endpoint that keeps
  `deal_stage_transitions` history accurate — prevents silently
  corrupting the audit trail via the general update path.
- No credentials, tokens, or secrets are stored in the frontend beyond
  the JWT itself in `localStorage` (the standard, documented pattern
  for a bearer-token SPA); no API keys or provider credentials touch
  the frontend at any point.
- **Known gap, not introduced by this phase:** `RBACMiddleware` exists
  but isn't attached to any route anywhere in the app (confirmed via
  `grep -n "'rbac'" routes/api.php` → no matches) — authorization
  currently only checks "is this a valid JWT for this tenant," not
  role/permission. Flagged plainly in `PROJECT_STATUS.md`; fixing it is
  a cross-cutting change spanning every route file, out of scope for a
  single-feature CRM phase per the directive's "work strictly
  phase-by-phase" rule, and is called out here so it isn't silently
  carried forward unaddressed.

## 12. Known limitations

- Lead sources and lead statuses remain plain string columns rather
  than their own admin-manageable tables (no `LeadSource`/`LeadStatus`
  model). Functionally fine (validated against a fixed `in:` rule
  list) but not admin-configurable without a code change.
- The pre-existing `DealStage` model (keyed by `lead_id`) was left
  exactly as-is — it is a lead's own stage-transition history, a
  different concept from the new `Deal` entity's own
  `DealStageTransition` (keyed by `deal_id`). Both are real and
  non-overlapping; flagging the naming similarity so it isn't mistaken
  for duplication later.
- Frontend drag-and-drop Kanban is not implemented — the directive
  called it nice-to-have; the shipped UI uses a stage-move
  dropdown/button per card instead, which is fully functional.
- RBAC route-level enforcement (see §11).

## 13. UNVERIFIED items

- **Production database is MySQL 8.0+; this environment has no MySQL
  server**, so all verification (migrations, seeding, live requests,
  the new Phase 2 tests) ran against SQLite. No known MySQL-specific
  SQL was introduced this phase (all new queries use the query
  builder/Eloquent, no raw `DATE_FORMAT()`-style calls), but running
  `php artisan migrate:fresh` and the test suite against real MySQL has
  not been done and should be the first check on a staging server.
- **No outbound network in this sandbox** — irrelevant to Phase 2
  specifically (no new AI/Telegram/webhook code this phase), carried
  over from prior phases' status.
- Load/stress testing of the new endpoints under concurrent traffic
  was not performed (the existing `Phase9LoadTest.php` load tests
  don't cover the new Deal/CRM-dashboard endpoints).

## 14. Deployment instructions

```bash
# 1. Extract the cumulative ZIP, then:
cd QUDRIX_TRAVEL_CRM_PHASE_2_COMPLETE/PROJECT

# 2. Backend
composer install --no-dev --optimize-autoloader
cp .env.example .env
# Edit .env: set DB_* for your real MySQL 8.0+ instance,
# APP_URL, and CORS_ALLOWED_ORIGINS to your frontend's real origin
# (e.g. https://app.yourdomain.com) — the app will 401/CORS-block
# every request from an unlisted origin by design.
php artisan key:generate
php artisan jwt:secret
php artisan migrate --force
php artisan db:seed --force   # prints the generated admin password ONCE — save it
php artisan serve --port=8000  # or configure php-fpm + nginx/apache for production

# 3. Frontend
cd ../frontend
cp .env.example .env
# Edit .env: set VITE_API_BASE_URL to your backend's real /api/v1 URL
npm install
npm run build     # outputs to dist/ — serve this as static files behind your web server
# or, for local development only:
npm run dev
```

## 15. Verification commands + expected results

```bash
# Backend syntax/tests
cd PROJECT
php -l routes/api.php app/Http/Controllers/DealController.php app/Http/Controllers/CrmDashboardController.php
# expect: "No syntax errors detected" for each

php artisan test
# expect: Tests: 97 passed (203 assertions)

php artisan route:list --path=v1 | grep -E "deals|crm/|customers/.*360|leads/.*"
# expect: to see the new deals/*, crm/dashboard, crm/conversion-funnel,
# crm/follow-ups/calendar, customers/{id}/360, and leads update/destroy routes

# Frontend
cd ../frontend
npm run build && npm run typecheck && npm run lint
# expect: build succeeds, typecheck silent, lint "0 errors" (1 known warning)

# Live smoke test (after starting `php artisan serve --port=8123` and
# `npm run preview` on the frontend, with matching CORS_ALLOWED_ORIGINS):
curl -X POST http://localhost:8123/api/v1/login -H "Content-Type: application/json" \
  -d '{"email":"admin@qudrix.local","password":"<seeded password>"}'
# expect: HTTP 200 with a JWT token
# then open http://localhost:4173 in a browser, log in, and confirm
# the dashboard/customers/leads/deals/tasks pages load with no errors
```

---

**PHASE 2 STATUS: COMPLETE**

**Regression:** PASS (97/97, 0 regressions)

**Next phase:** WAITING FOR OWNER APPROVAL
