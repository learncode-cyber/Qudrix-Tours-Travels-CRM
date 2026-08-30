# Phase 5 (Master Directive numbering) — Hajj/Umrah + Student Visa

**Date:** 2026-08-30
**Scope:** Directive §28 Phase 5 / §3.H–I (Hajj & Umrah, Student Visa), continuing from Phase 4.

## Three more mass-assignment bugs found and fixed (same class as Phase 2/4 findings)

`HajjController@update`, `DashboardController@update`, and `AutomationController@update` all did
`$model->update($request->all())` — no validation, and each model's `$fillable` includes `tenant_id`,
so a client could pass `tenant_id` in the request body and reassign the record to a **different tenant**,
or set any other fillable field to a nonsensical/malicious value with no type or business-rule checking.
This is the same shape of bug as the cross-tenant lookup issue fixed in Phase 4, just via mass-assignment
instead of a missing `where`. Fixed all three to validate an explicit field whitelist that excludes
`tenant_id`.

Also noted, not fixed (out of scope for this phase): `DashboardController@getKPI` returns hardcoded zeros
for every metric — a `Real Data Only` (Directive §27) violation. Flagged for the Reporting/Analytics
phase, where the real aggregation queries belong.

## What Phase 5 added (Directive §3.H–I checklists)

**Hajj & Umrah** — `HajjPackage`/`UmrahPackage` already existed as package *definitions*; the spec also
needs the operational side:
- `HajjUmrahGroup` — a specific departure (dates, capacity, group leader, agent/vendor) that a package
  actually runs as. `UmrahPackage` gained the `accommodations` column `HajjPackage` already had, for
  parity.
- `Pilgrim` — a complete per-person operational profile distinct from `Customer` (a booking can cover a
  family; each pilgrim needs their own room, transport, visa, and payment tracking): passport details,
  mahram, room number + hotel, transport assignment, linked `VisaApplication`, and payment tracking
  (`amount_due`/`amount_paid`/`balanceDue()`).
- Group reports (`GET /hajj-umrah-groups/{id}/report`): pilgrim counts by status, seats available, total
  due/paid/outstanding, and unassigned-room count — all computed from real rows.
- Notes/Documents (from Phase 2) extended to accept `pilgrim` as an attachable entity.

**Student Visa** — built as its own dedicated module (`StudentVisaApplication`), not shoehorned into the
generic `VisaApplication` used for travel visas, since the spec explicitly warns against treating it as a
generic visa record: student name/DOB, destination country, university, course, intake, a
purpose-built status pipeline (`inquiry → documents_pending → applied → offer_received →
visa_appointment_scheduled → visa_submitted → visa_approved/visa_rejected → enrolled`), offer letter
tracking, embassy appointment scheduling, visa status, assigned counsellor, and service fee/payment
status. Counselling notes and follow-ups reuse the existing `Note` (now whitelisted for
`student_visa_application`) and `Reminder` models rather than duplicating that infrastructure.

All new tables/columns are in
`database/migrations/2026_08_30_130000_create_phase5_hajj_umrah_student_visa_tables.php`.

## Verification

Same constraint as prior phases — no `vendor/`, no DB, no Packagist access in this session. `php -l`
passes with zero errors across `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `tests/`,
`resources/`; every controller referenced in `routes/api.php` confirmed to exist on disk. `composer
install` / `artisan migrate` / `artisan test` remain `UNVERIFIED — REQUIRES LOCAL/STAGING EXECUTION`.
