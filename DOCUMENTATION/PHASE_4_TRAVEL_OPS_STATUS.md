# Phase 4 (Master Directive numbering) — Bookings + Flights + Hotels + Visa

**Date:** 2026-08-30
**Scope:** Directive §28 Phase 4 / §3.E–G (Flight, Hotel, Visa Management), continuing from Phase 3.

Note on naming: `PHASE_4_MONITORING_AND_HEALTH.md` already exists under `PROJECT/docs/` from the earlier,
differently-numbered development cycle (unrelated — it's about webhook monitoring). This file is named
distinctly and lives in `DOCUMENTATION/` to avoid any clash.

## Bugs found and fixed while auditing this area

1. **`FlightController@store`**: validated `departure_date`/`arrival_date` with `'required|datetime'` —
   `datetime` is not a real Laravel validation rule (the correct one is `date`); every flight-creation
   request would have thrown `BadMethodCallException: Method ...::validateDatetime does not exist`.
2. **`FlightController@bookFlight`** and **`HotelController@bookHotel`** both did `Flight::findOrFail($id)`
   / `Hotel::findOrFail($id)` with no `tenant_id` scope, unlike every other lookup in the codebase — a
   real cross-tenant data leak in a multi-tenant app (tenant A could book a flight/hotel belonging to
   tenant B by guessing/enumerating IDs). Fixed both to scope by `$request->user->tenant_id`.

## What Phase 4 added (Directive §3.E–G checklists)

**Flights** — GDS-integration-ready fields on `flight_bookings`: `pnr` (auto-generated per booking group),
`cabin_class`, `baggage_allowance`, `fare_type`, plus `refund_status`/`refund_amount`/`cancelled_at` and a
`cancelFlightBooking` endpoint that releases the seat back to inventory. `flights` gained a nullable
`supplier_id` linking to the existing `Supplier` model — the seam a real GDS/airline API integration would
attach to. **No GDS API is implemented or invented** — the directive is explicit that connectors without a
supplied contract must stay architecture-only; this is exactly that seam, clearly commented as such.

**Hotels** — proper rate/availability granularity: `HotelRoomType` (per-hotel room types with their own
rate and room count, replacing the one-price-per-property model) and `HotelExtraService` (add-ons like
breakfast/spa, priced and selectable per booking via `HotelBookingExtraService`). `bookHotel` now accepts
an optional room type + extra service selection, falling back to the old property-level pricing when a
room type isn't specified so existing integrations don't break.

**Visa** — `embassy`, `appointment_date`, `expected_completion_date`, and `assigned_to` (staff) fields on
`visa_applications`; a `VisaDocumentRequirement` model lets an admin define the required-documents
checklist per destination country + visa type (directive: "Make visa workflows configurable by visa
type/country"); creating a visa application now auto-seeds a `VisaChecklistItem` per matching requirement,
with endpoints to view/update each item's status (missing/submitted/verified/rejected) and to (re)assign
staff.

All new columns/tables are in
`database/migrations/2026_08_30_120000_create_phase4_travel_ops_tables.php`.

## Verification

Same constraint as prior phases — no `vendor/`, no DB, no Packagist access in this session. `php -l`
passes with zero errors across `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `tests/`,
`resources/`; every controller referenced in `routes/api.php` confirmed to exist on disk. `composer
install` / `artisan migrate` / `artisan test` remain `UNVERIFIED — REQUIRES LOCAL/STAGING EXECUTION`.
