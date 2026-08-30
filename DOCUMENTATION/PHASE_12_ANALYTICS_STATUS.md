# Phase 12 (Master Directive numbering) — Behavioural Analytics + Executive Dashboard

**Date:** 2026-08-30
**Scope:** Directive §3.A (Executive Dashboard), §12 (Behavioral Analytics), §27 (Real Data Only),
§28 Phase 12.

## The most serious finding in the project so far: the database schema could never be built

While writing real aggregation queries I cross-referenced every model against every
`Schema::create(...)` in `database/migrations/`. **Nine core tables had no create migration anywhere in
the repository:**

`customers` · `leads` · `packages` · `payments` · `roles` · `role_user` · `branches` ·
`notifications` · `audit_logs` · `settings`

These are not peripheral — `customers` and `leads` are the two central CRM entities, `roles`/`role_user`
back the entire RBAC layer, `audit_logs` is what `AuditMiddleware` writes to on every mutating request,
and `packages` is referenced by a foreign key in the Phase 2 and Phase 3 migrations.

`php artisan migrate:fresh` therefore **fails on the very first phase migration**
(`create_phase1_tables` creates `communications`, which has a foreign key to `customers`). The schema
could never have been built as shipped — which means the original repository's claim that
`php artisan migrate:fresh --seed` passed was definitively false, as was the "68 database tables"
figure in `FINAL_AUDIT_REPORT.md`.

**Fixed** by adding `2024_01_01_000003_create_core_tables.php`. The filename is deliberate: it sorts
after `..._000002_create_users_table` and before `..._000003_create_phase1_tables`
("create_core" < "create_phase1"), which is the order the foreign keys require.

### Second migration-blocking bug: two tables created twice

`webhooks` and `webhook_logs` were each created by **two different migrations with conflicting
schemas** — `2024_01_01_000011_create_api_settings_table.php` and the later dedicated
`2024_08_17_000012` / `000014`. `migrate:fresh` would abort with *"Base table or view already exists"*.

The two definitions were genuinely different, not merely duplicated, and the `Webhook` / `WebhookLog`
models match the **earlier** shape. Rather than discard either, the later migrations were rewritten as
**additive**: they add the columns the webhook delivery services need (`api_key_id`, `secret`,
`retry_limit`, `last_triggered_status`, `delivery_id`, `message`, `retry_at`) onto the existing table,
each guarded by `Schema::hasColumn`, with a `hasTable` fallback that still creates a usable table if the
earlier migration is ever removed. Both feature sets survive.

### Bonus fix

`leads` now carries a nullable `customer_id`, which resolves the `Customer::leads()` relationship bug
flagged back in Phase 2 (it was a `hasMany` against a column that did not exist).

## What Phase 12 built

`BehavioralAnalyticsService` — every figure from a real query, no stubs:

- **Executive dashboard** (§3.A): revenue, outstanding amount, pending payment invoices, total/qualified/
  won leads, conversion rate, active bookings, today's follow-ups, upcoming departures, visa
  applications, flight/hotel bookings, Hajj-Umrah pilgrims, student visa applications, sales pipeline,
  6-month revenue trend, lead-source performance, per-staff performance, and real profit & loss.
- **Behavioural metrics** (§12): time-to-conversion (real join from lead creation to first booking),
  deal value distribution, follow-up effectiveness, engagement and read-rate by channel, repeat-customer
  count.
- Sales pipeline, revenue trend (gap-filled so a genuinely empty month reads as 0 rather than
  disappearing), lead-source performance, quotation funnel.

**Honest nulls.** Where a metric cannot be computed from what the system actually stores, it is returned
as `null` alongside an `unavailable_metrics` list explaining why — never as a zero that would read as a
real measurement. Example: conversion rate with no leads in the period.

## The `getKPI` fix (flagged in Phase 5)

`DashboardController@getKPI` returned a hardcoded `0` for all six KPIs regardless of the data — a direct
§27 violation. It now computes bookings, revenue, customers, and average booking value from real rows.
`customer_satisfaction` and `occupancy_rate` are returned as **null with a stated reason** (no CSAT
capture exists; occupancy needs per-date room inventory that isn't modelled yet) rather than fabricated.

## Verification

`php -l` passes with zero errors across `app/`, `bootstrap/`, `config/`, `database/`, `routes/`,
`tests/`, `resources/`; every controller referenced in `routes/api.php` exists on disk; no unguarded
duplicate `Schema::create` remains. Route controller-string backslash style normalised across the file.

Still `UNVERIFIED — REQUIRES LOCAL/STAGING EXECUTION` in this sandbox (no `vendor/`, no database):
`composer install`, `php artisan migrate:fresh --seed`, `php artisan test`. **With this phase's schema
fixes, `migrate:fresh` is expected to succeed for the first time** — that is the single most important
thing to verify in a real environment.
