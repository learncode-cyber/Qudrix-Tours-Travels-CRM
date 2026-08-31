# Phase 11 Report — Sales Strategies + Customer Memory + AI Copilot

Like Phase 10, this module's backend (`SalesStrategyController`,
`CustomerMemoryController`, `AiCopilotController`/`AiCopilotService`) was
built in a prior session and is built entirely on the Phase 9 gateway. It
had never been executed live or covered by a test. This phase's work
was: (1) audit it, (2) live-test every endpoint end-to-end, (3) fix the
one real bug it exposed, (4) write its automated test suite, (5) build
its frontend, (6) verify everything together.

## 1. What was implemented

- **Audit**: route-to-controller-method cross-reference (clean), plus a
  full read of `AiCopilotService`. Two guarantees stood out for close
  verification: (1) `CustomerMemory::scopeSafeForAi()` withholds any
  entry marked `is_sensitive` from every prompt — Directive S9's "do not
  store sensitive information unnecessarily" extended structurally to
  "do not transmit it unnecessarily either"; (2) `extractMemoryCandidates`
  returns candidates only — nothing is ever written to `customer_memories`
  by the AI itself, a human confirms each one through the ordinary
  `POST /customer-memories` endpoint.
- **Live backend verification**: full CRUD for sales strategies and
  customer memory, the strategy-key and memory-category validation, and
  — again exercised as **real round trips to Anthropic's live API**
  (`api.anthropic.com` reachable from this sandbox) with a deliberately
  invalid key — `POST /ai/leads/{id}/copilot`, which reached the real
  provider and got a genuine `401`, propagated honestly. Also confirmed
  `extract-memory`'s zero-HTTP-call short-circuit for a lead with no
  communications, and its real-call path once a communication existed
  (same honest 401). While setting up a realistic test scenario (a lead
  linked to a customer, so the Copilot has real communications to
  ground on), found a real bug: see §8/9.
- **Frontend** (new, first UI this module has ever had):
  - `SalesStrategiesPage.tsx` — CRUD with the methodology `key` picker
    populated from the API's own `available_keys`, activate/deactivate,
    delete.
  - Two new tabs added to the existing `AiLeadAssistantModal.tsx`
    (already built in Phase 10): **Copilot** (a live-assistance form
    showing the suggested next question, objection handling, recommended
    products, sentiment, and facts to verify, with the active strategy
    name shown) and **Extract Memory** (candidates listed with their
    evidence and a possibly-sensitive flag, each with its own "Confirm &
    Save" button that calls `POST /customer-memories` — nothing is saved
    until a human clicks it).
- **Automated tests**: `tests/Feature/Phase11SalesStrategyCopilotTest.php`
  — 11 tests, the first this module has ever had.

## 2. Files / modules changed

Backend:
- `app/Http/Controllers/LeadController.php` — added `customer_id` to
  `update()`'s validation (see §8/9).
- `tests/Feature/Phase11SalesStrategyCopilotTest.php` (new)

Frontend:
- `frontend/src/pages/SalesStrategiesPage.tsx` (new)
- `frontend/src/components/AiLeadAssistantModal.tsx` — added Copilot and
  Extract Memory tabs
- `frontend/src/api/endpoints.ts` — added wrappers for sales strategies,
  customer memory, and the two AI Copilot endpoints
- `frontend/src/types/index.ts` — added `SalesStrategy`, `CustomerMemory`,
  `AiCopilotAssistResult`, `AiMemoryCandidate`, `AiMemoryExtractionResult`
  and their supporting union types
- `frontend/src/App.tsx` — added `/sales-strategies` route
- `frontend/src/components/AppLayout.tsx` — added "Sales Strategies" nav
  entry

Documentation:
- `DOCUMENTATION/PHASE_11_REPORT.md` (this file)
- `DOCUMENTATION/API_DOCUMENTATION_COMPLETE.md` — Phase 11 addendum
- `CHANGELOG.md`, `PROJECT_STATUS.md` — updated

## 3. Database changes

None. `sales_strategies` and `customer_memories` already existed from the
prior session's migrations.

## 4. API changes

One: `PUT /api/v1/leads/{id}` now accepts `customer_id` (nullable,
`exists:customers,id`) in addition to its previous fields. No other route
or contract changed. See
`DOCUMENTATION/API_DOCUMENTATION_COMPLETE.md`'s Phase 11 addendum for the
full endpoint reference.

## 5. Frontend changes

One new page and two new tabs on an existing modal, described in §1.
Reused every existing shared component and utility rather than
introducing new ones.

## 6 & 7. Tests performed and results

**Automated (PASS):**
```
Tests:    209 passed (632 assertions)
Duration: 9.16s
```
(198 pre-existing + 11 new `Phase11SalesStrategyCopilotTest` tests, zero
failures, zero regressions.) The 11 new tests cover: sales strategy CRUD
+ its `key` enum validation (an unsupported methodology is rejected) +
tenant scoping; customer memory's `customer_id`-or-`lead_id` requirement
on both `index` and `store`, full CRUD, and its `category` enum
validation; **the `LeadController` fix as a regression test** — updating
a lead with `customer_id` now actually persists it; Copilot's honest
failure with no active provider; extract-memory's zero-HTTP-call
short-circuit (asserted via `Http::assertNothingSent()`); the Copilot
correctly reporting which active strategy it used; **a live assertion
via `Http::assertSent()` that a memory value marked sensitive never
appears in the actual outgoing prompt body while a non-sensitive one
does** — proof, not just trust in the code comment; and extract-memory
returning `stored: false` candidates that are confirmed to never
actually land in the `customer_memories` table.

**Manual/live (PASS):** Two full passes:
1. Direct curl calls with a real JWT against a live `php artisan serve`
   instance — full sales-strategy and customer-memory CRUD; confirmed
   `PUT /leads/{id}` silently dropped `customer_id` (reproducing the bug
   live before fixing it), then confirmed the fix persists it; confirmed
   `extract-memory` short-circuits for a lead with no linked customer/
   communications; then, with a real (invalid-key) Anthropic provider
   active, confirmed both `copilot` and (once a real communication
   existed) `extract-memory` reached the **real** `api.anthropic.com`
   and received a genuine `401`, propagated end-to-end as a clean `502`.
2. Headless-Chromium Playwright E2E against the built frontend served
   via `vite preview`, logged in as the real seeded admin: created a
   sales strategy through the UI, opened the AI Assistant modal from the
   Leads page, ran the Copilot tab (the honest 502 rendered correctly as
   an error banner) and opened the Extract Memory tab — zero
   `pageerror` events. A separate full-app sweep (all pre-existing pages
   plus this phase's changes) also completed with zero page errors.

**Build/typecheck (PASS):** `npx tsc --noEmit` silent; `npm run build`
succeeded (127 modules, no errors).

## 8 & 9. Bugs discovered and fixed

1. **`PUT /leads/{id}` silently dropped `customer_id`.**
   `LeadController::update()`'s validation whitelist
   (`name`/`email`/`phone`/`company`/`designation`/`source`/`priority`/
   `notes`/`assigned_to`) never included `customer_id`, even though
   `Lead::$fillable` has included it since Master Directive Phase 2 —
   whose own changelog entry explains the column exists specifically
   "so a customer's originating leads can be found without guessing."
   Practical effect: there was no way through the real API to link an
   existing lead to a customer after creation; a request that included
   `customer_id` in its body silently dropped it and returned the lead
   unchanged on that field. This blocked a real workflow this phase
   needed directly: the AI Copilot and memory extraction only see a
   lead's real communications when that lead has a `customer_id`, so a
   support agent trying to enable Copilot/memory features for an
   existing lead had no way to do so. Same bug class as Phase 4's
   `embassy_id` and Phase 7's `external_thread_id` — a real,
   schema/model-supported field silently absent from one specific
   controller's validation array. **Fixed** by adding
   `'customer_id' => 'nullable|exists:customers,id'` to the validation.

No other bugs found — every other endpoint in this module behaved
exactly as its code describes on first live execution.

## 10. Regression results

Full suite: 209/209 passing (was 198/198 before this phase — net +11,
zero failures introduced). Full-app Playwright sweep across all
pre-existing pages plus this phase's changes: zero `pageerror` events.
The one code change (`LeadController::update()`'s validation) only
widens what was previously silently rejected — no existing caller that
never sent `customer_id` is affected.

## 11. Security considerations

- **Sensitive memory is structurally withheld from AI prompts**, not
  just filtered by convention — verified this phase by inspecting the
  actual outgoing HTTP request body in a test, not merely trusting the
  `scopeSafeForAi()` comment.
- **The AI cannot write to customer memory on its own** — extraction
  only ever returns candidates a human must individually confirm via the
  normal, audited `POST /customer-memories` endpoint. Verified by
  asserting the database table stays empty after an extraction call.
- **Every memory write is attributable** — `created_by` is set from the
  authenticated user and the route sits behind the same `audit`
  middleware as every other protected write in this project.
- No new secrets, API keys, or credentials introduced.

## 12. Known limitations

- Same "no successful real AI completion verified" limitation as Phases
  9 and 10 — this sandbox has no valid AI provider key, so only the
  honest-failure path was exercised live; the success path (JSON
  parsing, strategy selection, sensitive-data filtering) is verified via
  `Http::fake` in the automated suite.
- **No customer-segment picker** for binding a `SalesStrategy` to a
  specific segment from the UI — the backend supports
  `customer_segment_id`, but there's no segment picker wired into this
  frontend yet (same gap flagged in the Phase 6 pricing-rules report).
- Same cross-cutting limitations as every prior phase (RBAC not
  route-enforced, MySQL-only in production/SQLite-only verified here) —
  see `PROJECT_STATUS.md`.

## 13. UNVERIFIED items

- A successful real AI completion for `copilot`/`extract-memory` against
  any provider with a valid key — no valid key was available in this
  sandbox.
- Production MySQL 8.0+ behavior — verified here against SQLite only.
- RBAC-level authorization on these endpoints — same cross-cutting,
  already-documented gap from Phase 1, not new to this phase.

## 14. Deployment instructions

Identical to every prior phase — no new migrations, no new environment
variables:
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
php artisan test --filter=Phase11SalesStrategyCopilotTest
# expect: Tests: 11 passed (47 assertions)

php artisan test
# expect: Tests: 209 passed (632 assertions)

# live curl (after login, with a Bearer token):
curl -X PUT /api/v1/leads/<id> -d '{"customer_id":<customer id>}'
# expect: 200, response data includes "customer_id": <customer id> (previously null)

curl -X POST /api/v1/ai/leads/<id>/copilot
# expect (no active provider): 502, "No active AI provider is configured for this tenant. ..."

cd frontend && npx tsc --noEmit && npm run build
# expect: build succeeds, typecheck silent
```
