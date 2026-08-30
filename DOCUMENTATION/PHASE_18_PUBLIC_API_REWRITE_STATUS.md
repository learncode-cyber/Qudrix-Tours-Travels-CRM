# Phase 18 — Full-Codebase QA + Public API Rewrite

**Date:** 2026-08-30
**Scope:** Directive §29 (audit every phase), §27 (Real Data Only), §37 (no fake functionality).

A full cross-check of every model against every migration, every route against every controller, and
every controller query against the real schema. It surfaced the last major broken area.

## The three public website-integration endpoints could never have worked

`PublicPackageController`, `PublicBookingController` and `PublicQuotationController` — the endpoints a
tenant's public website calls, and the ones `PHASE_1_STATUS.md` described as *"715 lines, REAL CODE"*
with *"20+ passing tests"* — were written against a **schema that does not exist**.

Verified column-by-column against the migrations:

| Controller wrote / read | Reality |
|---|---|
| `bookings.booking_reference` | column is `booking_number` |
| `bookings.total_price` | column is `total_amount` |
| `bookings.booking_status` | column is `status` |
| `bookings.created_by = 'website_api'` | **non-nullable integer FK to `users`** — a string insert |
| `booking_travelers.name` | columns are `first_name` / `last_name` |
| `packages.price`, `.duration_days`, `.capacity` | columns are `base_price`, `days`/`nights`; no `capacity` |
| `quotations.package_id`, `.travel_date`, `.number_of_travelers`, `.base_price`, `.total_price`, `.special_requirements` | **none of these exist on `quotations`** |
| `customers.source` | column did not exist |

Every one of the three would have thrown on its first call. The prior "tests passing" claim could not
have been true.

### And a third cross-tenant data leak

None of the three applied **any** tenant filter. `ApiKeyMiddleware` sets `$request->apiKey` (which
carries `tenant_id`), but the queries ignored it — so **any tenant's API key returned every tenant's
packages**, and could book against another tenant's inventory or attach a booking to another tenant's
customer by email match.

This is the third such leak found in this project (after the Phase 4 flight/hotel lookups and the Phase
15 traveller/itinerary controllers).

## What was done

All three rewritten against the verified schema, with:

- **Tenant taken from the API key, never from client input.** Every query, and the package lookup that
  gates booking, is scoped to `$request->apiKey->tenant_id`. Customer lookup-by-email is tenant-scoped
  too, so a shared address cannot cross-link tenants.
- **Prices derived from the package's real `base_price`**, never accepted from the request.
- **`created_by` made nullable** (migration) — a website-originated record genuinely has no CRM user
  behind it. A new `source` column (`crm` / `website` / `agent`) on bookings, quotations and customers
  keeps the two origins distinguishable in reporting instead of faking an internal creator.
- **A website quote request now creates a real `Lead`**, putting it into the actual sales pipeline
  rather than leaving an orphaned quotation. The quotation is created as a **draft** with proper
  `QuotationItem` line items.
- **The hardcoded 5%/10% "group discount"** in the old quotation controller was removed. Discounts
  belong to the auditable `PricingEngine` (Phase 6), not to an unlogged rule buried in a public
  controller. The response labels its figure *"indicative total based on list price"*.
- Sort whitelists now name columns that exist.

## Other audit results (clean)

- Every model has a backing table (the two apparent misses were a naive pluraliser: `analytics`,
  `offline_data`).
- Every controller referenced in every route file exists on disk (79 controllers).
- No unguarded duplicate `Schema::create` remains.
- No `$request->user()` method misuse remains outside explanatory comments.
- No mock/placeholder/TODO markers remain in `app/`.

## Verification

`php -l` clean across `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `tests/`, `resources/`.
Every column referenced by the rewritten controllers was **programmatically verified** to exist in the
migrations. Runtime execution (`composer install`, `migrate`, `test`) remains
`UNVERIFIED — REQUIRES LOCAL/STAGING EXECUTION`.
