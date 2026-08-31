# Phase 5 Report — Hajj & Umrah + Student Visa

Per `PROJECT_STATUS.md`, this module's backend (controllers, models,
migrations, routes) was built in a prior session but — like every
"backend complete (prior sessions)" phase — had never actually been
executed against a live database, called with a real HTTP request, or
covered by an automated test. This phase's work was: (1) audit it
statically, (2) live-test every endpoint end-to-end for the first time,
(3) fix the one real bug that surfaced, (4) write its automated test
suite, (5) build its frontend, (6) verify everything together.

## 1. What was implemented

- **Audit**: cross-referenced every `Route::apiResource(...)`/explicit
  route registration for this module against its controller's actual
  public methods (the same check that found 8 broken routes in Phase 4).
  Result: clean — every registered route has a matching controller
  method, and every controller method's `$validate()` fields match the
  real migration schema. No missing-method bugs this time.
- **Live backend verification**: ran the full workflow against a real
  SQLite-backed server for the first time — create Hajj package, create
  Umrah package, create a departure group against the Hajj package,
  register a pilgrim, assign a room, record a partial payment, pull the
  group report, then the complete student visa lifecycle (create →
  documents_pending → offer letter → embassy appointment → visa
  submitted). Found and fixed the one real bug below.
- **Frontend** (new, first UI this module has ever had):
  - `HajjUmrahPage.tsx` — three tabs: Hajj Packages (list + create +
    edit, since `PUT /hajj/{id}` exists), Umrah Packages (list + create
    only, since no update/destroy route exists for Umrah), Groups (list
    + create with package-type/status filters, links to a detail page).
  - `HajjUmrahGroupDetailPage.tsx` — Pilgrims tab (register a pilgrim,
    assign room, assign transport, record payment) and a Report tab
    (totals + by-status breakdown from `GET .../report`).
  - `StudentVisaPage.tsx` — list with application-status filters, create
    form, and four action modals (update status, record offer letter,
    schedule embassy appointment, update visa status). No counsellor
    assignment UI — there's no users-list endpoint wired into this
    frontend to build a real picker from, so it was left out rather than
    faked.
  - No delete/destroy button exists anywhere in this phase's UI — the
    backend genuinely has no destroy route for any of these five
    resources (hajj packages, umrah packages, groups, pilgrims, student
    visa applications). This is a real backend characteristic, not an
    oversight in the frontend.
- **Automated tests**: `tests/Feature/Phase5HajjUmrahStudentVisaTest.php`
  — 12 tests, the first this module has ever had.

## 2. Files / modules changed

Backend:
- `app/Http/Controllers/HajjController.php` — fixed `update()`'s status
  validation (see §8/9).
- `tests/Feature/Phase5HajjUmrahStudentVisaTest.php` (new)

Frontend:
- `frontend/src/pages/HajjUmrahPage.tsx` (new)
- `frontend/src/pages/HajjUmrahGroupDetailPage.tsx` (new)
- `frontend/src/pages/StudentVisaPage.tsx` (new)
- `frontend/src/api/endpoints.ts` — added wrappers for all 5 Phase 5
  resources and their action endpoints
- `frontend/src/types/index.ts` — added `HajjPackage`, `UmrahPackage`,
  `HajjUmrahGroup`, `HajjUmrahGroupReport`, `Pilgrim`,
  `StudentVisaApplication` and their status union types
- `frontend/src/utils/format.ts` — `getErrorMessage()` now also reads a
  backend `error` key, not just `message` (see §8/9 — this is a
  cross-cutting fix, not scoped to this phase's pages only)
- `frontend/src/App.tsx` — added `/hajj-umrah`,
  `/hajj-umrah/groups/:id`, `/student-visa` routes
- `frontend/src/components/AppLayout.tsx` — added "Hajj & Umrah" and
  "Student Visa" nav entries

Documentation:
- `DOCUMENTATION/PHASE_5_REPORT.md` (this file)
- `DOCUMENTATION/API_DOCUMENTATION_COMPLETE.md` — Phase 5 addendum
- `CHANGELOG.md`, `PROJECT_STATUS.md` — updated

## 3. Database changes

None. All tables (`hajj_packages`, `umrah_packages`, `hajj_umrah_groups`,
`pilgrims`, `student_visa_applications`) already existed from the prior
session's migrations and needed no schema change — the bug found this
phase was a validation/schema mismatch fixed in application code, not the
schema itself (see §8/9 for why altering the schema wasn't the right fix
here).

## 4. API changes

No new endpoints — all 5 resources' routes already existed. One
validation fix: `PUT /api/v1/hajj/{id}` now rejects `status: sold_out`
(never a real option) and accepts `status: discontinued` (the actual
third enum value) instead. See
`DOCUMENTATION/API_DOCUMENTATION_COMPLETE.md`'s Phase 5 addendum for the
full endpoint reference.

## 5. Frontend changes

Three new pages and their routes/nav entries, described in §1. Reused
every existing shared component (`Badge`, `Modal`, `Loading`,
`ErrorBanner`, `EmptyState`, `NotAvailable`, `StatCard`) and utility
(`formatCurrency`, `formatDate`, `getErrorMessage`, `statusTone`,
`titleCase`) rather than introducing new ones.

## 6 & 7. Tests performed and results

**Automated (PASS):**
```
Tests:    134 passed (381 assertions)
Duration: 6.69s
```
(122 pre-existing + 12 new `Phase5HajjUmrahStudentVisaTest` tests, zero
failures, zero regressions.) The 12 new tests cover: Hajj package
create/show/update/list + tenant scoping; Umrah package create/show/list
+ confirming no update route exists (405); group creation rejecting an
invalid package reference (404); group update + report aggregation
(pilgrim counts, seats available, amount due/paid/balance,
unassigned-room count) + tenant scoping; full pilgrim lifecycle (create,
update, assign room, assign transport, record payment, payment_status
flips to `paid`) + the at-capacity 400 rejection + tenant scoping; full
student visa lifecycle (create, update, status, offer letter, embassy
appointment, visa status, assign counsellor) + the 2-letter
`destination_country` validation + status filter + tenant scoping.

**Manual/live (PASS):** Two full passes:
1. Direct curl calls with a real JWT against a live `php artisan serve`
   instance (not the test DB) — create Hajj package (201), create Umrah
   package (201), create group (201), create pilgrim (201), assign room
   (200), record payment (200, `payment_status` correctly flipped to
   `partial`), group report (200, correct aggregation), full student
   visa lifecycle through all 5 action endpoints (all 200/201).
2. Headless-Chromium Playwright E2E against the built frontend
   (`npm run build` output served via `vite preview`) logged in as the
   real seeded admin: created a Hajj package, an Umrah package, a group,
   navigated to the group detail page, registered a pilgrim, assigned a
   room, loaded the report tab, created a student visa application, and
   updated its status — every step confirmed via the resulting DOM,
   zero `pageerror` events. A separate 15-page sweep across the entire
   app (all pre-existing pages plus these 3 new ones) also completed
   with zero page errors.

**Build/typecheck (PASS):** `npx tsc --noEmit` silent; `npm run build`
succeeded (116 modules, no errors).

## 8 & 9. Bugs discovered and fixed

1. **`HajjController::update()`'s status validation didn't match the
   database schema.** The validation rule was
   `'status' => 'sometimes|in:active,inactive,sold_out'`, but
   `hajj_packages.status` is a DB-level `enum('active','inactive',
   'discontinued')` from the original phase 0/1 migration. Setting
   `status: sold_out` passed Laravel's request validation (since the
   validator only checks against the list *given to it*, not the real
   column definition) and then crashed with
   `SQLSTATE[23000]: Integrity constraint violation: 19 CHECK constraint
   failed: status` — a 500-level failure from a request that looked
   perfectly valid. Caught immediately by this phase's own feature test
   (`test_hajj_package_crud_lifecycle`), not by static analysis or
   `php -l`. **Fixed** by correcting the validation rule to
   `in:active,inactive,discontinued`, matching the actual schema, rather
   than migrating the schema to add `sold_out` — no other part of the
   app referenced `sold_out` as a real status, so widening the schema
   would have been unjustified scope.
2. **`getErrorMessage()` only read a `message` key, silently swallowing
   `error`-keyed responses.** While building the pilgrim-registration
   form's error handling for the group-at-capacity case
   (`POST /pilgrims` → `400 {"error": "Group is at full capacity"}`),
   found that the shared `getErrorMessage()` helper used across the
   entire frontend only ever inspected `response.data.message`, never
   `response.data.error`. Every page in the app that surfaces a
   backend-returned `error`-keyed message (not just this phase's pages)
   was showing the generic fallback text instead of the real reason.
   **Fixed** in `utils/format.ts` to check both keys.

No other bugs found — the rest of this module's backend behaved exactly
as its code and schema describe on first live execution, a notably
cleaner result than Phase 4's 8-broken-route finding.

## 10. Regression results

Full suite: 134/134 passing (was 122/122 before this phase — net +12,
zero failures introduced). Full-app 15-page Playwright sweep (all pages
from Phases 2–5): zero `pageerror` events. No existing endpoint, page, or
test was modified in a way that changed its behavior — the two fixes in
§8/9 only change previously-broken/incorrect behavior (a validation rule
that couldn't have worked, and an error-message helper that was silently
dropping real backend text).

## 11. Security considerations

- Every new query in this module was already tenant-scoped by
  `where('tenant_id', $request->user->tenant_id)` in the prior session's
  code — confirmed by this phase's new tenant-scoping tests on every
  list endpoint (Hajj packages, groups, pilgrims, student visa
  applications) and by the group-detail cross-tenant 404 check.
- No new secrets, API keys, or credentials introduced.
- No new frontend-exposed data that shouldn't be — pilgrim financial
  fields (`amount_due`/`amount_paid`) and student visa `service_fee` are
  legitimate CRM data for staff users, same tier as existing
  quotation/invoice amounts already shown elsewhere in the app.

## 12. Known limitations

- **No counsellor-assignment UI.** The backend has
  `POST /student-visa-applications/{id}/assign-counsellor`, but building
  a real picker needs a users-list endpoint this frontend doesn't call
  anywhere yet. Left out rather than faked with a fabricated list — flag
  for whichever future phase wires up a general staff/user directory.
- **No delete/destroy UI for any Phase 5 resource**, because the backend
  genuinely has no destroy route for any of the five (Hajj/Umrah
  packages, groups, pilgrims, student visa applications) — this mirrors
  the backend's actual capability, not a frontend gap.
- **List endpoints for this module return no pagination metadata**
  (`{"data": [...]}` only, unlike some Phase 4 endpoints that also
  return a `pagination` object) — the frontend doesn't build pagination
  controls against them as a result. Flagged in the API addendum for
  awareness, not treated as a bug since nothing in the app currently
  depends on pagination for these lists.
- Same cross-cutting limitations as every prior phase (RBAC not
  route-enforced, MySQL-only in production/SQLite-only verified here, no
  outbound network in this sandbox) — see `PROJECT_STATUS.md`.

## 13. UNVERIFIED items

- Production MySQL 8.0+ behavior — verified here against SQLite only, per
  the project's standing sandbox limitation. Nothing in this phase's SQL
  is MySQL-portability-risky (no raw `DATE_FORMAT()` or similar), but
  this has not been run against a real MySQL server.
- RBAC-level authorization on these endpoints (any authenticated user in
  the tenant can currently perform any action) — this is the same
  cross-cutting, already-documented gap from Phase 1, not new to this
  phase.

## 14. Deployment instructions

Identical to every prior phase — no new migrations, no new environment
variables, no new dependencies:
```
composer install --no-dev --optimize-autoloader
# configure .env: DB_CONNECTION, DB_DATABASE (MySQL 8.0+ in production), etc.
php artisan key:generate --force
php artisan jwt:secret --force
php artisan migrate --force
php artisan db:seed --force        # capture the printed admin password
php artisan serve                  # or your real web server

# frontend
npm install
# set VITE_API_BASE_URL in frontend/.env to the backend's real URL
npm run build
# serve dist/ via your static host / reverse proxy
```

## 15. Verification commands + expected results

```bash
php artisan test --filter=Phase5HajjUmrahStudentVisaTest
# expect: Tests: 12 passed (73 assertions)

php artisan test
# expect: Tests: 134 passed (381 assertions)

# live curl (after login, with a Bearer token):
curl -X POST /api/v1/hajj -d '{"name":"...","duration_days":21,"price":4500,"max_capacity":40}'
# expect: 201, {"data": {...,"status":"active"}}

curl -X POST /api/v1/pilgrims -d '{"hajj_umrah_group_id":<full-group-id>,"name":"..."}'
# expect: 400, {"error":"Group is at full capacity"}

cd frontend && npx tsc --noEmit && npm run build
# expect: build succeeds, typecheck silent
```
