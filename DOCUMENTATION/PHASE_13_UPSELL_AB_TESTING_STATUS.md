# Phase 13 — Upsell/Cross-sell Engine + Sales Script A/B Testing

**Date:** 2026-08-30
**Scope:** Directive §11 (Upsell/Cross-sell), §14 (A/B Testing), §28 Phase 13.

## Upsell / Cross-sell Engine (§11)

`UpsellEngine` is **rule-based, not model-driven** — the directive requires recommendations to be "based
on configured products/rules and actual availability", so this is deliberately not an AI feature.

- `UpsellRule` — admin-configured: trigger type (flight/hotel/tour/visa/hajj/umrah/transport/any),
  what to recommend, suggested price, priority, and a `requires_availability_check` flag.
- `forBooking()` detects what a booking **actually contains** by querying the real join tables
  (`flight_bookings`, `hotel_bookings`, `visa_applications`), never by assumption. It then:
  - skips anything the booking already has, and
  - **skips any rule whose recommendation has no real availability**. A rep is never shown an option
    they cannot fulfil.
- Types the CRM genuinely does not stock (insurance, tour guide, generic add-ons) are returned with an
  explicit note — *"not tracked as inventory in this system; confirm with the supplier before promising
  it"* — rather than being silently reported as available.
- Every recommendation shown and its outcome is recorded, so `effectiveness()` reports real acceptance
  rates and real upsell revenue.

## A/B Testing (§14)

`AbTestingService` measures the four things the directive names: response rate, conversion, booking value,
time-to-close.

Three decisions worth noting:

1. **Deterministic assignment.** Variants are picked from a hash of `experiment_id:lead_id`, not
   `rand()`. The same lead always lands in the same variant, the split is reproducible, and a
   unique constraint on `(ab_experiment_id, lead_id)` means a lead can never be counted twice or flip
   between variants mid-experiment.
2. **It refuses to declare a winner on a meaningless sample.** Below 30 assignments per variant, or with
   under a 1-percentage-point gap, `determineWinner()` returns `decided: false` with the reason. The real
   rates are still shown. An A/B tool that calls a winner on five leads is worse than one that admits it
   doesn't know yet.
3. **It does not overstate what it computed.** The winner note says explicitly this is *"a descriptive
   result, not a statistical significance test"* — because that is what it is.

`start()` refuses to run an experiment with fewer than two active variants.

## Verification

`php -l` passes with zero errors across `app/`, `bootstrap/`, `config/`, `database/`, `routes/`,
`tests/`, `resources/`; every controller referenced in `routes/api.php` exists on disk.
`composer install` / `migrate` / `test` remain `UNVERIFIED — REQUIRES LOCAL/STAGING EXECUTION`.
