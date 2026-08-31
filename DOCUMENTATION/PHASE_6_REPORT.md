# Phase 6 Report — Custom Package Builder + Pricing Engine

Like Phase 5, this module's backend (`PricingRuleController`,
`PackageBuilderController`, the `PricingEngine` and `InventoryResolver`
services, and their models/migrations) was built in a prior session but
had never actually been executed against a live database, called with a
real HTTP request, or covered by an automated test. This phase's work
was: (1) audit it statically, (2) live-test every endpoint and the full
build-a-package workflow end-to-end for the first time, (3) write its
automated test suite, (4) build its frontend, (5) verify everything
together. **No bugs were found in this module** — a first for this
directive's phase-by-phase audits.

## 1. What was implemented

- **Audit**: route-to-controller-method cross-reference (clean, as with
  Phase 5) plus a close read of `PricingEngine::calculate()` and
  `InventoryResolver::resolveAll()`, since these two services carry real
  financial and inventory-integrity logic, not just CRUD. Confirmed: the
  resolver only ever prices from real `HotelRoomType`/`Flight`/`Transport`
  rows scoped to the requesting tenant, and the engine only ever applies
  rules that exist in the `pricing_rules` table for that tenant — neither
  can fabricate a component or a price adjustment.
- **Live backend verification**: created real hotel/room-type, flight,
  and transport inventory, then ran the complete workflow against a live
  server — created a pricing rule, previewed a calculation and confirmed
  the math, built a package from three real components and confirmed the
  aggregated cost and the applied markup, confirmed insufficient-seats
  rejection, deactivated and deleted the rule and confirmed the preview's
  math changed accordingly, and ran the `save_as_package` and
  `create_quotation` paths (including quotation line-item generation).
  Every single call behaved exactly as the code says. No bug to fix this
  phase.
- **Frontend** (new, first UI this module has ever had):
  - `PricingRulesPage.tsx` — list with activate/deactivate/delete, a
    creation form whose visible fields adapt to the selected `factor`
    (season dates for `season`, group-size bounds for `group_size`,
    booking-lead-time bounds for `booking_timing`), and a "Preview
    Calculation" tool showing the full applied-rules breakdown.
  - `PackageBuilderPage.tsx` — a dynamic component list (add/remove
    rows), each row typed hotel/flight/transport with a real inventory
    picker for that type (hotel rows chain a hotel select into a
    room-type select, since `reference_id` for a hotel component is a
    room type id, not a hotel id), quantity, a build action showing the
    resolved line items and pricing breakdown, and optional
    save-as-package / create-quotation checkboxes.
  - Added `listTransports` (the backend has had a full transports CRUD
    route since Phase 4's route-gap fixes, but no frontend page had ever
    called it) — needed here to populate the builder's transport picker.
  - No lead/customer picker exists anywhere in this frontend yet, so
    `create_quotation`'s required `lead_id` and the optional
    `customer_id` are plain ID text fields with a hint pointing at the
    Leads page, rather than a fabricated dropdown.
- **Automated tests**:
  `tests/Feature/Phase6PricingPackageBuilderTest.php` — 11 tests, the
  first this module has ever had.

## 2. Files / modules changed

Backend:
- `tests/Feature/Phase6PricingPackageBuilderTest.php` (new)
- No application code changed — no bug was found to fix.

Frontend:
- `frontend/src/pages/PricingRulesPage.tsx` (new)
- `frontend/src/pages/PackageBuilderPage.tsx` (new)
- `frontend/src/api/endpoints.ts` — added wrappers for pricing rules,
  preview, package-builder build, and `listTransports`
- `frontend/src/types/index.ts` — added `Transport`, `PricingRule`,
  `PricingAppliedRule`, `PricingPreviewResult`,
  `PackageBuilderComponentInput`, `PackageBuilderResolvedLine`,
  `PackageBuilderResult`
- `frontend/src/App.tsx` — added `/pricing-rules`, `/package-builder`
  routes
- `frontend/src/components/AppLayout.tsx` — added "Package Builder" and
  "Pricing Rules" nav entries

Documentation:
- `DOCUMENTATION/PHASE_6_REPORT.md` (this file)
- `DOCUMENTATION/API_DOCUMENTATION_COMPLETE.md` — Phase 6 addendum
- `CHANGELOG.md`, `PROJECT_STATUS.md` — updated

## 3. Database changes

None. All tables (`pricing_rules`, `pricing_calculation_logs`, plus the
`packages` table's existing `is_custom_built`/`components`/`built_by`/
`built_for_customer_id` columns) already existed from the prior session's
migrations.

## 4. API changes

None — every route already existed and behaved correctly. See
`DOCUMENTATION/API_DOCUMENTATION_COMPLETE.md`'s Phase 6 addendum for the
full endpoint reference, including the two request-shape details worth
calling out for anyone integrating against this: `PUT /pricing-rules/{id}`
only accepts `{name, adjustment_type, adjustment_value, priority,
is_active}` — the matching conditions are set once at creation — and a
hotel component's `reference_id` in `POST /package-builder/build` must be
a `hotel_room_type_id`, not a `hotel_id`.

## 5. Frontend changes

Two new pages and their routes/nav entries, described in §1. Reused every
existing shared component and utility rather than introducing new ones.

## 6 & 7. Tests performed and results

**Automated (PASS):**
```
Tests:    145 passed (425 assertions)
Duration: 7.93s
```
(134 pre-existing + 11 new `Phase6PricingPackageBuilderTest` tests, zero
failures, zero regressions.) The 11 new tests cover: pricing rule CRUD +
tenant scoping; preview correctly applying a matching percentage rule,
correctly skipping a non-matching rule and an inactive rule, and correctly
compounding two rules in priority order (`1000 → +10% → 1100 → +50 fixed →
1150`, verified exactly); package build resolving real multi-type
inventory into the correct aggregate cost, rejecting a component that
belongs to another tenant, rejecting a flight with insufficient seats,
`save_as_package` producing a real `Package` row with the correct custom-
build flags, `create_quotation` requiring `lead_id` and — when given one —
producing a real `Quotation` with the correct line items and total.

**Manual/live (PASS):** Two full passes:
1. Direct curl calls with a real JWT against a live `php artisan serve`
   instance — created a hotel + room type, a flight, and a transport;
   created a pricing rule and confirmed the preview math (15% of 1000 =
   150, final 1150); built a full 3-component package (hotel×3 + flight×2
   + transport×2 = $2500 base) and confirmed the saved package's
   `base_price` reflected the 15%-marked-up $2875; confirmed a
   999-seat request against a 200-seat flight correctly 422'd; deactivated
   then deleted the rule and confirmed the preview and a subsequent build
   both reverted to the unmarked-up base cost; ran the full
   `create_quotation` path against a real lead and confirmed the
   resulting quotation's `total_amount` and line items.
2. Headless-Chromium Playwright E2E against the built frontend served via
   `vite preview`, logged in as the real seeded admin: created a pricing
   rule through the UI, ran a preview and confirmed the applied-rules
   table rendered, then on the Package Builder page filled in
   destination/date/group size, selected a real transport from the
   picker, and built a package — confirmed the resolved line items and
   final price rendered, zero `pageerror` events. A separate full-app
   sweep (all pre-existing pages) also completed with zero page errors.

**Build/typecheck (PASS):** `npx tsc --noEmit` silent; `npm run build`
succeeded (118 modules, no errors).

## 8 & 9. Bugs discovered and fixed

**None.** This is the first phase under this directive where the audit
and full live-testing pass found zero bugs in the module's backend —
every endpoint, validation rule, and calculation behaved exactly as its
code and database schema describe on first live execution. Reported
honestly per the project's standing rule rather than manufacturing a
finding to fill this section.

## 10. Regression results

Full suite: 145/145 passing (was 134/134 before this phase — net +11,
zero failures introduced). Full-app Playwright sweep across all
pre-existing pages plus this phase's two new ones: zero `pageerror`
events. No existing file was modified except adding new frontend
wrappers/types/routes/nav entries — no existing behavior changed.

## 11. Security considerations

- Confirmed by this phase's own tests: `InventoryResolver` rejects a
  component belonging to another tenant with a clean `422`, not a
  cross-tenant data leak or silent wrong-price calculation — verified
  directly with a real second-tenant hotel/room-type fixture.
- `PricingRuleController` and `PackageBuilderController` were already
  tenant-scoped by `where('tenant_id', $request->user->tenant_id)`,
  confirmed by this phase's new tenant-scoping test on the pricing rules
  list.
- Every pricing calculation — preview or as part of a build — is logged
  to `pricing_calculation_logs` with the acting user id, base cost,
  context, and full rule breakdown. There is no "unlogged" pricing path.
- No new secrets, API keys, or credentials introduced.

## 12. Known limitations

- **No customer-segment picker.** `PricingRule.factor = customer_segment`
  can be created from the UI, but there's no segment picker to attach a
  `customer_segment_id` condition to it from this frontend — the backend
  supports it, the UI doesn't expose it yet. Flagged rather than faked.
- **No lead/customer picker in the Package Builder.** `create_quotation`
  requires a real `lead_id`; entered as a plain numeric field with a
  hint, not a search dropdown, since no lead-search endpoint is wired
  into this frontend yet.
- **Pricing rule `update` can't change its matching conditions from the
  UI**, matching the real backend limitation (`PUT /pricing-rules/{id}`
  only accepts name/adjustment/priority/is_active) — to change a rule's
  season or group-size bounds, delete and recreate it. This mirrors the
  API, not a frontend gap.
- Same cross-cutting limitations as every prior phase (RBAC not
  route-enforced, MySQL-only in production/SQLite-only verified here, no
  outbound network in this sandbox) — see `PROJECT_STATUS.md`.

## 13. UNVERIFIED items

- Production MySQL 8.0+ behavior — verified here against SQLite only.
  Nothing in this phase's queries is MySQL-portability-risky.
- RBAC-level authorization on these endpoints — same cross-cutting,
  already-documented gap from Phase 1, not new to this phase.

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
php artisan test --filter=Phase6PricingPackageBuilderTest
# expect: Tests: 11 passed (44 assertions)

php artisan test
# expect: Tests: 145 passed (425 assertions)

# live curl (after login, with a Bearer token and real inventory created):
curl -X POST /api/v1/pricing-rules/preview -d '{"base_cost":1000,"group_size":2}'
# expect: 200, {"data": {"final_price": ..., "applied_rules": [...]}}

curl -X POST /api/v1/package-builder/build -d '{"destination":"...","travel_date":"...","group_size":2,"components":[{"type":"transport","reference_id":<id>,"quantity":999}]}'
# expect: 422, {"errors": {"components": ["Not enough capacity on transport #<id>."]}}

cd frontend && npx tsc --noEmit && npm run build
# expect: build succeeds, typecheck silent
```
