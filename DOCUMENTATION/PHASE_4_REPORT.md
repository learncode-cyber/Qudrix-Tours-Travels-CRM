# QUDRIX Travel CRM — PHASE 4 REPORT
## Travel Operations (Flights, Hotels, Visa, Booking Management)

**Date:** September 2, 2026
**Environment note (unchanged):** no PHP/MySQL/network here — static analysis only, run Section 6 yourself for real verification.

---

## 1. Audit result: the best-covered phase so far
Unlike Phases 2 and 3, every model in this phase's scope (`Flight`, `Hotel`, `VisaApplication`, `Transport`, `Destination`, `Booking` + their booking-detail tables) already had a matching migration — the earlier model-to-table cross-check came back clean. The existing controllers are real, working CRUD, not stubs. This phase was mostly bug-fixing and closing specific gaps against your spec, not rebuilding a broken foundation.

## 2. Bugs found and fixed

### 2.1 `'datetime'` is not a real Laravel validation rule (2 occurrences)
`FlightController::store()` (`departure_date`, `arrival_date`) and `TransportController::store()` (`pickup_date`) all validated with `'required|datetime'`. Laravel has no `datetime` rule — the correct one is `date`. An unrecognized rule name throws `BadMethodCallException`, so **every flight and every transport creation would have fatally errored**. Fixed both; swept the rest of `app/Http/Controllers/` for the same pattern and found no more.

### 2.2 `Booking::$fillable` was missing `group_booking_id`
The column and the `belongsTo(GroupBooking::class)` relation both exist, but `group_booking_id` was never added to `$fillable`. `GroupBookingController::addBookingToGroup()` calls `$booking->update(['group_booking_id' => ...])` — Eloquent's `update()` silently drops non-fillable fields rather than erroring, so this endpoint would return a success response while **never actually linking the booking to the group**. Fixed by adding the field to `$fillable`.

### 2.3 Visa approve/reject had no state guard
`approveVisa()` could be called on a visa still in `pending` status (never submitted), skipping the submission step entirely. Added a guard requiring `status === 'submitted'` first, matching the same status-transition-guard pattern already used by `Quotation`/`Proposal` elsewhere in the app.

## 3. Gaps closed against the Phase 4 spec (net new, not bugs)
- **Visa: Embassy, Appointment, Staff assignment, Rejection** — all four were explicitly listed in your spec but had no columns or endpoints at all (only `approve` existed). Added `embassy_name`, `appointment_date`, `assigned_to` columns + `scheduleAppointment()`, `assignStaff()`, `rejectVisa()` endpoints.
- **Booking: Refund** — `cancelBooking()` previously just flipped status with no refund trail. Now accepts an optional `refund_amount`, creates a real `Payment` record (`status: refunded`) linked to the booking, and updates `payment_status` — so a refund is auditable/shows up in payment stats rather than disappearing.

## 4. What was NOT built this phase, and why
- **Hotel/Flight live API integration (GDS, real hotel inventory APIs)** — no credentials/contracts exist. The manual-entry CRUD (add flights/hotels directly) is real and functional; live third-party inventory sync is architecture-ready but **BLOCKED** per your own rule, same as Phase 3's PDF/email items.
- **Visa document checklist/verification workflow** — `documents` (JSON) column exists and is functional for storing document metadata, but a structured checklist-with-verification-status system would need its own small model (similar to Custom Fields from Phase 2). Didn't build it this pass to keep the phase bounded — flagging as a good Phase 5 companion item since Hajj/Umrah/Student Visa will need document checklists too, and building one reusable implementation beats three different ad-hoc ones.
- **Room blocks (hotel group allotments)** — `Hotel`/`HotelBooking` support individual-booking room counts but not pre-blocked room allotments for groups. Real gap against the spec; deferred, same reasoning as above (Hajj/Umrah group departures in Phase 5 will need the same concept — worth designing once, not twice).

## 5. Regression check performed
Full controller/route resolution audit re-run across both route files (0 bare strings, all references resolve); re-checked every `apiResource` call in `routes/api.php` for the stats-shadowing bug from Phase 3 — none found in this phase's additions (all new routes are multi-segment, e.g. `/visas/{id}/reject`, which don't collide with `apiResource`'s single-segment `show` route).

## 6. Verified / Unverified / Blocked

✅ **VERIFIED (static):** all changes syntactically consistent, all routes resolve, migration ordering correct (visa extension migration runs after `phase4_tables` which creates the base table).
⚠️ **UNVERIFIED:** real migration execution, real request behavior — same caveat as every prior phase.
❌ **BLOCKED:** GDS/live hotel-flight inventory APIs (no credentials).

## 7. Verification — run these yourself

```bash
cd PROJECT
composer install && cp .env.example .env
php artisan key:generate && php artisan jwt:secret
php artisan migrate:fresh
php artisan serve

# Confirm the datetime validation fix (should accept, not 500):
curl -X POST localhost:8000/flights -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" \
  -d '{"airline_code":"BA","flight_number":"BA123","departure_airport":"LHR","arrival_airport":"JFK","departure_date":"2026-12-01","arrival_date":"2026-12-01","departure_time":"10:00:00","arrival_time":"13:00:00","aircraft_type":"777","total_seats":300,"price_per_seat":500,"currency":"USD"}'

# Confirm group_booking_id fillable fix actually persists:
curl -X POST localhost:8000/groups/1/bookings -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" -d '{"booking_id":1}'
curl localhost:8000/bookings/1 -H "Authorization: Bearer <TOKEN>"   # group_booking_id should now be set
```

## 8. Next phase
Stopping here per the workflow. Next up is **Phase 5 (Hajj/Umrah + Student Visa)** — given the deferred document-checklist and room-block items above genuinely overlap with Hajj/Umrah's needs, I'll likely propose building those as shared components during that audit rather than Hajj-specific one-offs, and will flag it clearly before doing so rather than silently expanding scope.
