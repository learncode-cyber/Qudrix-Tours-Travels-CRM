# Phase 6 (Master Directive numbering) — Custom Package Builder + Dynamic Pricing Engine

**Date:** 2026-08-30
**Scope:** Directive §28 Phase 6 / §6–7 (Custom Package Builder, Dynamic Pricing Engine), continuing from
Phase 5.

This phase deliberately builds the **deterministic, rule-based foundation** the directive requires before
any AI involvement: "Build a rule-based pricing engine first... Never allow an LLM to directly decide
financial values without validation" (§7) and "Never allow AI to invent inventory or pricing" (§6). The
AI-assisted natural-language package builder is Phase 10 and will call into this same engine rather than
replacing it.

## Bug found and fixed

`TourController` was routed with `apiResource('tours', ...)->only([..., 'update'])` but had **no
`update()` method at all** — every `PUT /tours/{id}` request would have thrown
`BadMethodCallException: Method App\Http\Controllers\TourController::update does not exist`. Added it,
matching the validation/tenant-scoping pattern already used by `show()`.

## What Phase 6 added

**Dynamic Pricing Engine** (`app/Services/PricingEngine.php`) — `PricingRule` records (tenant-scoped,
factor = season/demand/group_size/customer_segment/booking_timing, percentage-or-fixed adjustment,
priority-ordered) are evaluated against a calculation context (travel date, group size, customer segment,
booking lead time) via `PricingRule::matches()`. Every matching active rule is applied in a fixed,
deterministic order and the full breakdown — base cost, each rule applied with its exact amount, running
price after each step, final price — is written to `pricing_calculation_logs` on every single calculation,
satisfying the directive's "final price calculation must remain deterministic and auditable" requirement.
`PricingRuleController` gives admin CRUD plus a `/pricing-rules/preview` endpoint to test a calculation
without attaching it to anything (still logged).

**Custom Package Builder** (`PackageBuilderController@build`) — takes an explicit list of components
(`hotel` → `HotelRoomType`, `flight` → `Flight`, `transport` → `Transport`), looks each one up against
**real inventory scoped to the tenant**, and rejects the request outright if availability is insufficient
(never fabricates availability). Sums the real unit costs, runs the total through `PricingEngine` for a
transparent, logged markup, and can optionally:
- save the assembled itinerary as a reusable `Package` (extended with `is_custom_built`, `components`
  json, `built_by`, `built_for_customer_id`), and/or
- generate a real draft `Quotation` with one `QuotationItem` per component (using the Phase 3
  `cost_price` field) plus a single labeled "pricing adjustment" line item referencing the specific
  `pricing_calculation_logs` row it came from, so a human reviewing the quote can trace every dollar back
  to its source.

All new tables/columns are in
`database/migrations/2026_08_30_140000_create_phase6_pricing_package_builder_tables.php`.

## Verification

Same constraint as prior phases — no `vendor/`, no DB, no Packagist access in this session. `php -l`
passes with zero errors across `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `tests/`,
`resources/`; every controller referenced in `routes/api.php` confirmed to exist on disk. `composer
install` / `artisan migrate` / `artisan test` remain `UNVERIFIED — REQUIRES LOCAL/STAGING EXECUTION`.
