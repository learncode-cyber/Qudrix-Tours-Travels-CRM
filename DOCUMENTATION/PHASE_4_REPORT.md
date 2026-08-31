# Phase 4 Report — Travel Operations

**Master Directive numbering** — see `DOCUMENTATION/PHASE_2_REPORT.md` for
the disambiguation note about older, unrelated "Phase 2/3/4" documents
in this folder from prior development cycles.

**Date:** 2026-08-31 · **Branch:** `claude/qudrix-travel-crm-master-0opkmx`

---

## 1. What was implemented

An audit against the directive's Phase 4 checklist (Flights, Hotels,
Visa, Bookings) found the core entities already existed from prior
sessions. The real gaps closed this phase:

- **Booking calendar** — a date-range query endpoint over bookings.
- **Embassy** — a real entity (name, address, contact, processing
  time) instead of a bare free-text string on `VisaApplication`.
- **Room blocks** — group hotel-inventory holds, distinct from the
  existing per-guest booking flow.
- **Visa/passport expiry reminder system** — a scheduled + on-demand
  sweep that creates reminders for anything expiring soon.
- **Document support for flight/hotel bookings** — the existing
  polymorphic document system's type whitelist didn't cover them.
- **Package CRUD** — discovered missing entirely while live-testing the
  booking creation form (see §8).
- **8 pre-existing broken CRUD routes**, found by a systematic scan
  triggered by discovering the first instance of this bug class while
  building this phase's Visa update/destroy methods (see §8).
- **The frontend's Travel Operations module** — packages, bookings (+
  calendar + detail), flights (+ seat booking), hotels (+ room types +
  room blocks), visas (+ embassies + expiry-check trigger).

## 2. Files / modules changed

Backend (`PROJECT/`):
- New: `app/Models/Embassy.php`, `app/Models/RoomBlock.php`,
  `app/Http/Controllers/EmbassyController.php`,
  `app/Http/Controllers/RoomBlockController.php`,
  `app/Http/Controllers/PackageController.php`,
  `app/Services/ExpiryReminderService.php`,
  `app/Console/Commands/CheckExpiryReminders.php`,
  `database/migrations/2026_08_31_010000_create_phase4_travel_ops_additions.php`,
  `tests/Feature/Phase4TravelOpsTest.php`
- Modified: `app/Models/VisaApplication.php` (`embassy_id`),
  `app/Http/Controllers/BookingController.php` (`calendar`, `destroy`),
  `app/Http/Controllers/DocumentController.php` (whitelist),
  `app/Http/Controllers/VisaController.php` (`update`, `destroy`,
  `checkExpiryReminders`, `embassy_id` validation),
  `app/Http/Controllers/HotelController.php` (`update`, `destroy`),
  `app/Http/Controllers/FlightController.php` (`destroy`),
  `app/Http/Controllers/TransportController.php` (`show`, `update`,
  `destroy`), `app/Http/Controllers/DestinationController.php`
  (`destroy`), `app/Http/Controllers/SupplierController.php` (`update`,
  `destroy`), `app/Http/Controllers/CustomerController.php` (`delete`
  renamed to `destroy`), `app/Http/Controllers/TaskController.php`
  (`delete` renamed to `destroy`), `routes/console.php` (daily
  schedule), `routes/api.php`

Frontend (`frontend/`, new files): `PackagesPage.tsx`,
`BookingsPage.tsx`, `BookingDetailPage.tsx`, `BookingsCalendarPage.tsx`,
`FlightsPage.tsx`, `HotelsPage.tsx`, `HotelDetailPage.tsx`,
`VisasPage.tsx`, plus new types/endpoints/nav items.

## 3. Database changes

New migration `2026_08_31_010000_create_phase4_travel_ops_additions.php`:
- `embassies` table (tenant-scoped, soft-deletable).
- `visa_applications.embassy_id` — nullable FK to `embassies`, added
  alongside the existing `embassy` string column (kept for backward
  compatibility with existing data).
- `room_blocks` table (tenant-scoped; FKs to `hotels`,
  `hotel_room_types`, nullable `group_booking_id`).

No existing table's data or existing columns were altered or dropped.

## 4. API changes

See `DOCUMENTATION/API_DOCUMENTATION_COMPLETE.md`'s "MASTER DIRECTIVE
PHASE 4 ADDENDUM" for the full contract. Summary:

- `GET /bookings/calendar?from=&to=`
- `GET/POST/PUT/DELETE /embassies[/{id}]`
- `GET/POST/GET/DELETE /room-blocks[/{id}]`, `POST /room-blocks/{id}/release`
- `POST /visas/check-expiry-reminders`
- `GET/POST/PUT/DELETE /packages[/{id}]` (new — see §8)
- `POST /documents` accepts `flight_booking`/`hotel_booking` types
- 8 previously-registered-but-broken routes now actually work:
  `DELETE /customers/{id}`, `DELETE /tasks/{id}`, `DELETE /bookings/{id}`,
  `PUT/DELETE /hotels/{id}`, `GET/PUT/DELETE /transports/{id}`,
  `DELETE /flights/{id}`, `DELETE /destinations/{id}`,
  `PUT/DELETE /suppliers/{id}`, `PUT/DELETE /visas/{id}`

## 5. Frontend changes

New pages: `/packages`, `/bookings`, `/bookings/:id`,
`/bookings/calendar`, `/flights`, `/hotels`, `/hotels/:id`, `/visas`
(with an Embassies tab). Sidebar extended with all of these. The
`/flights` "Book Seat" flow and flight-creation form were corrected
after live testing exposed three contract mismatches in my own
specification to the page's builder (see §8/§9).

## 6 & 7. Tests performed and results

- `php artisan test`: **122/122 passing** (109 pre-existing + 13 new,
  zero regressions).
- Frontend `npm run build`/`typecheck`/`lint`: clean throughout (fixed
  twice, after each round of live-testing bugs — see §8).
- **Live end-to-end, twice**: booted the real server + built frontend
  and drove a real headless browser through the actual UI — created a
  package, a customer, a flight (with real date/time/currency fields),
  a hotel, an embassy — then created and confirmed a booking and
  watched it appear correctly in the calendar three months forward,
  triggered the on-demand expiry-reminder sweep, and confirmed the
  flight "Book Seat" flow's real request/response after fixing it. A
  full 15-page sweep across every page built in Phases 2–4 (not just
  this phase's) confirmed zero uncaught JS errors anywhere in the app.

## 8 & 9. Bugs discovered and fixed

1. **8 registered CRUD routes across the entire app 500'd with "method
   does not exist"** the moment anything called them — a bug class
   first noticed while adding `VisaController::update()`/`destroy()`
   (both were entirely missing despite `apiResource('visas', ...)`
   registering routes for them), which prompted a systematic scan of
   every `apiResource()` registration in the app against its
   controller's actual public methods. Found and fixed 8 instances
   total: `customers`/`tasks` had a method literally named `delete()`
   instead of Laravel's expected `destroy()`; `bookings`, `hotels`,
   `transports`, `flights`, `destinations`, and `suppliers` were each
   missing one or more of `show`/`update`/`destroy` outright. None of
   these were introduced this phase — they'd been latent since whenever
   each controller was written — but every one was invisible to
   `php -l` and would only surface the instant a real DELETE/PUT/GET
   request hit that specific route, which apparently had never
   happened before this phase's live testing.
2. **`VisaController::store` silently dropped `embassy_id`** — added
   to the model's fillable and the migration, but not to the
   controller's validation whitelist — so creating a visa application
   with a real embassy reference never actually saved it. Fixed
   alongside adding `update()`.
3. **`Package` had no CRUD endpoint at all.** Found live-testing the
   booking creation form: the package `<select>` had zero options and
   no way to ever get any, because `GET /api/v1/packages` didn't
   exist — despite `Booking.package_id` and `QuotationItem.package_id`
   depending on a real package existing. This blocked the single most
   central Phase 4 workflow (creating a booking) entirely through the
   UI. Built a minimal `PackageController` (distinct from the existing
   AI/quotation-driven package-builder controllers) and a matching
   frontend page.
4. **Three contract mismatches in my own specification** to the
   frontend build for `/flights`, all caught by live-testing (not by
   `npm run build`/`typecheck`, since they were runtime data-shape/
   validation issues): `POST /flights/book` actually takes
   `{travelers: [id, ...]}` and auto-assigns seats — I'd specified a
   single `booking_traveler_id` + explicit `seat_number`, which the
   API rejects outright; `POST /flights` requires `currency`, which I
   never mentioned; and `departure_time`/`arrival_time` require
   `H:i:s` format, but the native `<input type="time">` only produces
   `HH:MM` with no seconds. Fixed the request payloads, the flight
   form (added the missing currency field, append `:00` to submitted
   times), and the Book Seat modal (traveler ID list instead of a
   single traveler + seat number).

Bug 1 in particular illustrates why this engagement's "actually run it,
don't just lint it" standard matters at the scale of a whole codebase,
not just the feature being actively built: these 8 routes existed
since earlier phases, passed every syntax check, and were simply never
exercised by a real DELETE/PUT/GET request until this phase's
end-to-end testing happened to be the first to try them.

## 10. Regression results

Full backend suite re-run after every fix: **122/122 passing**. No
regressions in any of the 109 pre-existing tests (Phases 0–3, including
the Phase 2 CRM and Phase 3 Sales/Quotation tests).

## 11. Security considerations

- All new endpoints sit behind the existing `app.jwt` + `tenant` +
  `audit` middleware group; every query is explicitly `tenant_id`-scoped
  (confirmed for `Embassy`, `RoomBlock`, `Package`, and the expiry
  reminder service, which iterates tenants explicitly rather than
  querying across all of them at once).
- `RoomBlockController::release` validates `rooms` against
  `remainingRooms()` server-side (max rule + an explicit re-check) —
  cannot release more rooms than remain blocked, even if the client
  sends a stale count.
- `ExpiryReminderService` is idempotent by construction (checks for an
  existing pending reminder on the same `remindable_type`/
  `remindable_id` before creating one) — safe to run the on-demand
  endpoint or the daily schedule arbitrarily often without spamming
  duplicate reminders.
- Same known gap carried from Phases 2–3, not introduced here: RBAC
  middleware exists but isn't attached to any route — see
  `PROJECT_STATUS.md`.

## 12. Known limitations

- **Room blocks don't mutate `HotelRoomType.available_rooms`.**
  Deliberate scoping decision: wiring a group-inventory ledger into the
  existing, already-tested per-guest booking decrement logic is a
  larger, riskier change than this phase's mandate; room blocks are a
  separate ledger staff manage explicitly. Documented in both the API
  addendum and the model's own doc comment so it isn't mistaken for an
  oversight later.
- **The flight "Book Seat" UI has no cross-entity picker** — staff must
  type a booking ID and comma-separated traveler IDs by hand (found on
  the booking's own detail page). Building a real picker needs a
  booking-search endpoint that doesn't exist yet; flagged in the UI
  itself rather than faked.
- **PackageController is intentionally minimal** — plain CRUD only, no
  pricing engine, no AI-assisted building. That's real functionality
  that belongs to Phase 6 (Custom Package Builder + Pricing Engine)
  per the directive's own roadmap; this phase only had to unblock
  Bookings from having zero usable packages, not build the full
  package-management feature set.
- Same RBAC gap as prior phases (see §11).

## 13. UNVERIFIED items

- **Production database is MySQL 8.0+**; this sandbox has no MySQL
  server, so verification ran against SQLite as in every prior phase.
  No MySQL-specific SQL was introduced this phase.
- **No outbound network in this sandbox** — irrelevant to this phase's
  scope (no new external integrations).
- The daily `reminders:check-expiry` schedule itself was verified by
  running the command directly (`php artisan reminders:check-expiry`),
  not by actually waiting for Laravel's scheduler to fire it at
  06:00 — there is no long-running scheduler process in this sandbox.
  Confirm on a real server with `php artisan schedule:work` or a cron
  entry running `php artisan schedule:run` every minute.

## 14. Deployment instructions

Same as Phase 2/3 (`DOCUMENTATION/PHASE_2_REPORT.md` §14) plus one
addition: **register the Laravel scheduler** so
`reminders:check-expiry` actually runs daily — add this cron entry on
the production server:

```
* * * * * cd /path/to/PROJECT && php artisan schedule:run >> /dev/null 2>&1
```

Everything else (extract ZIP, `composer install`, migrate, seed, serve;
`npm install && npm run build` for the frontend) is unchanged.

## 15. Verification commands + expected results

```bash
cd PROJECT
php artisan test
# expect: Tests: 122 passed (308 assertions)

php artisan route:list --path=v1 | grep -E "bookings/calendar|embassies|room-blocks|packages|check-expiry"
# expect: to see all the new routes listed

php artisan reminders:check-expiry
# expect: "Created N visa expiry reminder(s) and M passport expiry reminder(s)."

cd ../frontend
npm run build && npm run typecheck && npm run lint
# expect: build succeeds, typecheck silent, lint 0 errors (1 known warning)
```

Live smoke test (`php artisan serve --port=8123` + `npm run preview`,
matching `CORS_ALLOWED_ORIGINS`): log in, create a package, a customer,
a flight (fill every field including Currency), a hotel, an embassy;
create a booking referencing the package and customer, confirm it, and
check it appears on `/bookings/calendar` in the correct month.

---

**PHASE 4 STATUS: COMPLETE**

**Regression:** PASS (122/122, 0 regressions)

**Next phase:** WAITING FOR OWNER APPROVAL
