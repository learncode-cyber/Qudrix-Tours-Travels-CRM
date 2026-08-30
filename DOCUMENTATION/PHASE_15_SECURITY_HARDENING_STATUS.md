# Phase 15 — Security Hardening + Access Logging

**Date:** 2026-08-30
**Scope:** Directive §19 (Security), §23 (Auditability), §28 Phase 15.

## Four real vulnerabilities found and fixed

### 1. `RateLimitMiddleware` fataled on every public route

`resolveRequestSignature()` called `$request->user()->id`. This application never calls `Auth::login()`
— it authenticates through the custom `JwtAuth` middleware, which sets `$request->user` as a **property**.
Laravel's `$request->user()` **method** is therefore always `null`, so this was
`Call to a member function id() on null` — a hard 500 — on every route running `rate.limit` without
`jwt.auth`. That is all three public surfaces: `routes/api-public.php`, the Phase 3 public quotation
links, and the health endpoints.

Fixed to read the property with a `guest` fallback, and rewritten to support per-route limits
(`rate.limit:5,1`), tighter guest defaults, `Retry-After` / `X-RateLimit-*` headers, and a signature that
includes the path so one abused endpoint cannot exhaust another's budget.

### 2. `TenantMiddleware` leaked tenants across requests

It called `Model::addGlobalScope('tenant', ...)` on the **Eloquent base class** with a closure capturing
that request's `$user`. That registration is **static and process-wide**, so under any long-running
worker (Octane, queue workers) every subsequent request in the process was filtered by the **first**
request's tenant id — a cross-tenant data leak in exactly the setups used for performance.

It was also broken outright: it applied `where tenant_id` to every model including the many join tables
with no such column (`quotation_items`, `role_user`, `taggables`, `invoice_items`, `booking_travelers`,
`booking_itineraries`, …), producing `Unknown column 'tenant_id'`.

Removed. Tenant isolation is enforced explicitly instead — 63 of 74 controllers already scope every
query with `->where('tenant_id', $request->user->tenant_id)`, which is per-request, correct for tables
without the column, and visible at the call site. The removal is documented in the middleware itself so
nobody reinstates it.

### 3. Two controllers had no tenant scoping at all

Removing the (broken) global scope exposed that `TravelerController` and `ItineraryController` used bare
`BookingTraveler::findOrFail($id)` / `BookingItinerary::findOrFail($id)`. Their tables have **no
`tenant_id` column**, so they were never protected by the global scope either — any tenant could read or
modify another tenant's travellers and itineraries by guessing an id. Both now scope through the parent
booking with `whereHas('booking', fn ($q) => $q->where('tenant_id', ...))`.

### 4. Login had no brute-force protection and leaked account existence

Added per-`email`+`IP` lockout (5 failures / 15 minutes, configurable). The key includes the source IP
deliberately: keying on email alone would let an attacker lock any user out globally just by hammering
their address. Every failure is recorded with its real reason (`unknown_email`, `bad_password`,
`inactive_account`, `locked_out`) **for defenders**, while the client response stays byte-identical
either way — so the endpoint cannot be used to enumerate valid addresses. A successful login clears the
streak and now records `last_login_at` (the column existed but was never written).

## Access logging (§19)

New `AccessLogMiddleware`, applied to the **whole `api` group** in `bootstrap/app.php` rather than
per-route, so a new route cannot forget to opt in. It records exactly what §19 lists — IP, URL, method,
user agent, status code, timestamp — plus duration, and flags suspicious requests: 401, 403, 429, any
5xx, and unusually slow calls.

Two deliberate choices:
- **Request bodies are never stored.** They contain passwords and provider credentials. Query strings
  *are* stored but with sensitive keys redacted, so a token in a URL never lands in the log table.
- **Logging can never break the request it observes** — the write is wrapped in a try/catch that degrades
  to a `Log::warning`.

`SecurityHeaders` was also moved to the `api` group for the same reason (it was previously opt-in and
most routes did not opt in).

Admin read-only views: `/security/access-logs`, `/security/audit-logs`, `/security/failed-logins`,
`/security/summary` — mounted under the configurable admin prefix from Phase 0.

`failed-logins` is deliberately **not** tenant-scoped: a failed login happens before any tenant is known,
and enumeration attempts against non-existent addresses have no tenant at all. It is protected by the
admin route prefix instead.

## Verification

`php -l` passes with zero errors across `app/`, `bootstrap/`, `config/`, `database/`, `routes/`,
`tests/`, `resources/`; every controller referenced in `routes/api.php` exists on disk.

The four fixes above are **reviewable by reading the code** — the null-method-call, the static global
scope, the unscoped `findOrFail`s, and the missing lockout are all structural. Runtime confirmation
(`composer install`, `migrate`, `artisan test`, an actual 429) remains
`UNVERIFIED — REQUIRES LOCAL/STAGING EXECUTION`.
