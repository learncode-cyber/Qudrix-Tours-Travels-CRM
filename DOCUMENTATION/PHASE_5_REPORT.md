# QUDRIX Travel CRM — PHASE 5 REPORT
## Hajj/Umrah + Student Visa

**Date:** September 2, 2026
**Environment note (unchanged):** no PHP/MySQL/network here — static analysis only, run Section 7 yourself for real verification.

---

## 1. Audit result
Confirmed what the very first Phase 0 audit predicted: Hajj/Umrah was bare-bones (package CRUD only — no Pilgrims, Groups, Departures, or status tracking beyond a booking's generic `status` field), and Student Visa had **zero code of any kind**. Model-to-table check came back clean otherwise — no missing tables anywhere in the project as of this phase.

## 2. Bugs found and fixed

### 2.1 `HajjController::update()` had no validation
Called `$package->update($request->all())` directly. `$fillable` still guards which columns get written, so this wasn't a mass-assignment vulnerability, but it meant no type/range checking — a non-numeric `price` or negative `max_capacity` would be silently stored as-is. Added the same validation rules `store()` already uses, as `sometimes`.

### 2.2 `UmrahController` had no `update()` method at all
Umrah packages, once created, could never be edited. Added it, mirroring the fixed Hajj version.

### 2.3 `RitualCheckpoint` model existed with no controller anywhere
This model (tracks a pilgrim's progress through ritual stages — Ihram, Tawaf, Sa'i, etc.) has existed since an early phase, but nothing in the entire codebase could create or update one. Built `RitualCheckpointController` to actually use it.

## 3. Reused instead of duplicated (flagged in Phase 4 report, followed through here)
- **Departures** — rather than a new `Departure` model, extended the existing `GroupBooking` (built in Phase 4) with `departure_date`, `return_date`, `package_type`, `package_id`. A Hajj/Umrah departure batch *is* a group of bookings traveling together — that's exactly what `GroupBooking` already models.
- **Document readiness / required documents** — built one shared `DocumentRequirement`/`DocumentSubmission` system (entity-agnostic, same `entity_type`+`entity_id` pattern as Tags and Custom Fields from Phase 2) instead of a Hajj-specific version and a separate Student-Visa-specific version. Both modules use it via `entity_type = 'hajj_booking' | 'umrah_booking' | 'student_visa'`.
- **Pilgrims** — the spec's "Pilgrims" is already covered by `BookingTraveler` (Phase 3/4), which has passport, DOB, nationality, and emergency contact fields. Did not create a redundant `Pilgrim` model — a pilgrim is a traveler on a Hajj/Umrah-typed booking.
- **Student Visa follow-up** — uses the existing `Task` system (`related_entity_type`/`related_entity_id`), same as how Customer Timeline (Phase 2) aggregates from it. No separate follow-up table.

## 4. New Phase 5 features implemented
- **Student Visa** (fully new): `StudentVisaApplication` model/migration/controller — student profile, university/course/intake, application deadline tracking with a `deadline_soon` filter, counselor assignment, offer letter tracking, visa status, and a **guarded status-transition sequence** (`advanceStatus()`) that blocks skipping steps (e.g. can't jump straight from `inquiry` to `enrolled`), matching the same transition-guard pattern used by Quotation/Proposal/Visa. `withdrawn`/`visa_rejected` are reachable as exit states from anywhere, since real applications do get abandoned or rejected out of sequence.
- **Document Checklist** (fully new, shared): define per-tenant document requirements per entity type, submit, verify, reject, and a `readiness()` endpoint returning whether all mandatory documents are verified — directly answers the spec's "document readiness" (Hajj/Umrah) and "required documents"/"document verification" (Student Visa) items.
- **Ritual Checkpoints**: controller wiring the previously-orphaned model — create/list per booking, update status with automatic `completed_date` stamping.
- **Package stats**: `getPackageStats()` for both Hajj and Umrah (total/active packages, total active capacity) — the spec's "Reports" item, scoped to what's honestly derivable from existing data (didn't fabricate revenue/booking-rate figures that would need deeper booking-to-package linkage than currently exists).

## 5. What was NOT built, and why
- **Hajj/Umrah booking-to-package revenue reports** — `HajjPackage`/`UmrahPackage` link to bookings via the generic `Booking.package_id`, but there's no dedicated pipeline connecting a booking's payments back to a specific Hajj/Umrah package for revenue-per-package reporting. Real gap; would need either a `booking_type`-aware query layer or denormalized reporting tables. Deferred rather than approximated.
- **Offer letter / visa document file storage** — `DocumentSubmission.file_reference` is a string column ready to hold a path or URL, but no actual file upload/storage integration exists yet (no S3/local disk wiring beyond the `config/filesystems.php` scaffold from Phase 1). Architecture-ready, **BLOCKED** on a decision about storage backend.

## 6. Regression check performed
Full controller/route resolution audit re-run (0 bare strings, all classes resolve) and the complete model-to-table cross-check re-run with all tables through Phase 5 — came back clean, no missing tables anywhere in the project. Applied the Phase 3/4 stats-route-ordering fix proactively to the new `/hajj/stats` and `/umrah/stats` routes from the start, rather than introducing the bug and fixing it later.

## 7. Verified / Unverified / Blocked

✅ **VERIFIED (static):** all new/modified files consistent, all routes resolve, migration ordering correct (all three new Phase 5 migrations depend only on tables that existed since Phase 2).
⚠️ **UNVERIFIED:** real migration execution and request behavior, as always in this sandbox.
❌ **BLOCKED:** document file storage/upload (needs a storage-backend decision — S3 vs. local — before it's more than the `file_reference` column that exists now).

## 8. Verification — run these yourself

```bash
cd PROJECT
composer install && cp .env.example .env
php artisan key:generate && php artisan jwt:secret
php artisan migrate:fresh
php artisan serve

# Confirm UmrahController::update() now exists:
curl -X PUT localhost:8000/umrah/1 -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" -d '{"price": 2500}'

# Confirm document readiness aggregation:
curl -X POST localhost:8000/document-requirements -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" \
  -d '{"entity_type":"student_visa","name":"Passport copy","is_mandatory":true}'
curl localhost:8000/document-readiness/student_visa/1 -H "Authorization: Bearer <TOKEN>"

# Confirm guarded status transition rejects an invalid jump:
curl -X POST localhost:8000/student-visas/1/advance -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" -d '{"status":"enrolled"}'
# should 400 with "Cannot move from 'inquiry' directly to 'enrolled'"
```

## 9. Next phase
Stopping here per the workflow. Next is **Phase 6 (AI Custom Package Builder + Pricing Engine)** — heads up before I start: the "AI" part of package building will hit the same wall flagged back in Phase 3/4 — no AI provider credentials exist yet (that's Phase 9's job per your roadmap). I'll build the deterministic pricing engine and package-assembly logic for real this phase, and scaffold the AI-recommendation hook as an interface that Phase 9's provider layer plugs into later, rather than faking an AI response now or silently skipping ahead to build Phase 9 early.
