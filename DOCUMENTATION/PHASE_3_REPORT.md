# QUDRIX Travel CRM — PHASE 3 REPORT
## Sales + Quotation System

**Date:** September 2, 2026
**Environment note (unchanged):** no PHP/MySQL/network in this sandbox. Static analysis only — run Section 7 yourself for real verification.

---

## 1. Audit findings (bugs in existing code, found before/while building)

### 1.1 The public website API had no authentication at all, despite claiming to
`routes/api-public.php`'s own doc-comment says "Authentication: API Key (Bearer token) + Secret header," but the route group only applied `['api', 'throttle:api']` — `ApiKeyMiddleware` was never actually attached. **Fixed:** added `api.key.auth` to the group (with the pre-existing `/health` check explicitly exempted, since a health endpoint shouldn't require a key).

### 1.2 `ApiKeyMiddleware` would have rejected every valid key
It checked `$apiKey->status !== 'active'`, but the `api_keys` table has `is_active` (boolean) — `status` doesn't exist as a column, so this always evaluated to `null !== 'active'` (true), rejecting every request regardless of the key's real state. This is almost certainly *why* the middleware was never wired into the route group — it likely never worked in testing. **Fixed:** now checks `!$apiKey->is_active`.

### 1.3 No tenant resolution on the public API at all
Even a working `ApiKeyMiddleware` never propagated `tenant_id` anywhere, so public controllers had no reliable way to know which tenant's data to read/write. **Fixed:** middleware now sets `app()->instance('tenant_id', ...)` and `$request->tenant_id`, mirroring how `TenantMiddleware` does it for the authenticated flow.

### 1.4 `PublicQuotationController` was writing to fields that don't exist
It set `base_price`, `total_price`, `travel_date`, `number_of_travelers`, `special_requirements`, `quoted_budget`, `package_id` on `Quotation` — none of these were in `$fillable` or had backing columns. Eloquent silently drops non-fillable mass-assigned fields, so **every public quote request silently lost this data**, and it also never set the required `tenant_id` or `lead_id` (both `NOT NULL` on `quotations`), which would have thrown a database integrity error on every single request. **Fixed:** added the missing columns via migration, added the `package()` relation, rewrote the controller to auto-create/find a `Lead` (a public quote request is fundamentally a new lead, matching how the rest of the CRM models the sales funnel) and to read `tenant_id` from the now-functioning API-key resolution.

### 1.5 Route-ordering bug: `apiResource` registered before static routes, silently swallowing them
`Route::apiResource('quotations', ...)` (registers `GET /quotations/{quotation}`) was declared *before* `Route::get('/quotations/stats', ...)`. Laravel matches routes in registration order, so any request to `/quotations/stats` would match `{quotation} = "stats"` instead and hit the wrong handler. Found and fixed the same bug in **`tasks`** and **`bookings`** (checked every `apiResource` call in the file — `communications` uses `->only(['index','store'])` so it has no `show` route to collide with, and was fine as-is). **Fixed:** reordered all three so static routes are declared first.

### 1.6 `QuotationController::getQuotationStats()` called a nonexistent method
`->average('total_amount')` — Eloquent's method is `avg()`, not `average()`. Would fatal on every call. **Fixed.**

---

## 2. New Phase 3 features implemented

- **Invoices** (fully new: model, migration, controller) — generated from a signed Proposal (pulling totals from its Quotation), listing, void (blocked if payments exist), stats (invoiced/collected/outstanding).
- **Payments** (model existed, zero controller existed) — record against an invoice, list, update status, stats by method, with `Invoice::recalculatePaidAmount()` keeping invoice status (`sent` → `partially_paid` → `paid`) in sync automatically.
- **Commission tracking** — `commission_rate` set on a `Proposal` at signing time (real input, not invented), computed into `commission_amount` on completed payments against invoices tied to that proposal. If no rate was ever configured, the field stays `null` — not a fabricated 0%.
- **Quote versioning** — `createVersion()` clones a quotation + its line items, linked via `parent_quotation_id`/`version`, rather than mutating a quotation a customer may already be reviewing.
- **Approval workflow** — tenant-configurable via `Settings` (`quotation_approval_threshold`); quotations above it are blocked from being sent until `approve()`'d. Not hardcoded — a business with no threshold configured sees no change in behavior.
- **Customer acceptance** — public `POST /quotations/{number}/respond` (`accept`/`reject`), gated on the quotation being in `sent` status and not expired.

## 3. What was NOT built this phase, and why
- **PDF quotation/invoice generation** — Laravel has no built-in PDF library; would need `barryvdh/laravel-dompdf` or similar added to `composer.json`. Didn't add it silently — flagging it here rather than either faking PDF output or quietly expanding scope without your sign-off. Trivial to add next.
- **Email quotation delivery** — needs a configured mail driver/provider (SMTP, SES, etc.) with real credentials. Architecture-ready (Invoice/Quotation have all the data a mail template needs) but **BLOCKED** until credentials exist, per your own rule.
- **True multi-currency conversion** — `currency` field exists and is stored per quotation, but converting between currencies needs a live FX rate provider. Did not fake a conversion table with invented rates.

## 4. Verified / Unverified / Blocked

✅ **VERIFIED (static):** all new/modified files are syntactically consistent, every route resolves to a real controller method, migration FK ordering is dependency-correct, no bare/unqualified controller-string routes anywhere.
⚠️ **UNVERIFIED (environment limitation):** actual migration execution, real request/response behavior, whether `Schema::table(...)->change()` (used to make `quotations.created_by` nullable) runs cleanly on your MySQL version.
❌ **BLOCKED:** PDF generation (no package installed — needs your decision on `barryvdh/laravel-dompdf` vs. alternatives), email delivery (no mail credentials).

## 5. Regression check performed
Re-ran the full controller/route resolution audit across **both** `routes/api.php` and `routes/api-public.php` this time (previous phases only checked `api.php`) — 0 bare controller strings, all 7 unique controller references across both files resolve to real classes. This is how the API-key auth gap was caught in the first place.

## 6. Files changed/added this phase
**New:** `Invoice`, `InvoiceController`, `PaymentController`, migrations `000005b` (invoices), `000005c` (payments/proposals commission fields), `000004b` (quotations extension).
**Modified:** `Quotation`, `Payment`, `Proposal` models; `QuotationController`, `ProposalController`, `PublicQuotationController`, `ApiKeyMiddleware`; `routes/api.php`, `routes/api-public.php`.

## 7. Verification — run these yourself

```bash
cd PROJECT
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate:fresh
php artisan serve

# Smoke test route-ordering fix (should return stats JSON, not a 404/model-not-found):
curl localhost:8000/tasks/stats -H "Authorization: Bearer <TOKEN>"
curl localhost:8000/bookings/stats -H "Authorization: Bearer <TOKEN>"
curl localhost:8000/quotations/stats -H "Authorization: Bearer <TOKEN>"

# Smoke test public API key auth actually enforces now:
curl localhost:8000/api/v1/health   # should work without a key
curl -X POST localhost:8000/api/v1/quotations   # should now 401 without a key (previously silently open)
```

Report back what `php artisan migrate:fresh` says — the `Schema::table()->change()` calls in this phase's migrations are the first thing in this project that needs that capability, and I can't confirm it works without real MySQL.

## 8. Next phase
Per the workflow, this stops here. Say the word and I'll move to **Phase 4 (Travel Operations: Flights, Hotels, Visa, Booking Management)** — I'll audit what already exists there the same way before building, since Phase 1-3 have all turned up more gaps than the handover docs claimed.
