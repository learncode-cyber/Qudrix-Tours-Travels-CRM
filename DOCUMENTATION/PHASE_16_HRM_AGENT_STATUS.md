# Phase 16 — HRM + B2B Agent Management

**Date:** 2026-08-30
**Scope:** Directive §3.M (Task & HR), §3.L (B2B Agent Management), §28. Closes two of the modules the
competitor benchmark has (HRM: 9 screens; Agent Management: 10 screens) that Qudrix was missing entirely.

## HRM (§3.M)

Builds on the Phase 0 `Employee` skeleton with the parts the directive lists: attendance, leave,
holidays, payroll, approvals.

- **Attendance** — real check-in/check-out with a unique constraint of one row per employee per day.
  "Late" is derived from a **configurable** `HRM_WORK_START_TIME`, not a hardcoded office hour, because
  working hours differ per agency and country.
- **Leave** — types with quotas, requests with a real inclusive day count, and an approve/reject
  decision trail (`decided_by`, `decided_at`, `decision_note`). **Approving leave writes real
  `attendance` rows** for each covered day, so attendance reports and payroll agree instead of quietly
  contradicting each other.
- **Payroll** — a draft run is generated from **real employee salaries and real attendance counts** for
  the period. Two deliberate choices:
  - Allowances and deductions start at 0 and must be entered by finance. The system does not invent
    them.
  - An employee with no `basic_salary` set is recorded as 0 **and flagged in the item note**
    (*"needs finance input"*), so a missing salary is visible rather than silently paid as zero.
  - `net_pay` is always **derived** (`basic + allowances − deductions`), never accepted from the
    request. Run totals are recomputed from the real line items on every edit and again at approval.
  - Only a `draft` run can be edited; approval stamps who and when.

## B2B Agent Management (§3.L)

`Agent` is deliberately distinct from `Vendor`: a Vendor is a supplier the agency **buys from**, an Agent
**sells on the agency's behalf** and earns commission. The directive calls for both to stay separate and
they now are.

- **Registration → KYC → approval.** An agent starts `pending` / `not_submitted` and **cannot transact
  until approved**. Approval is refused unless KYC is `verified` — the gate is enforced server-side, not
  left to the UI.
- **Commission cannot be tampered with.** `recordCommission` takes only a `booking_id`; the amount is
  **derived** from the booking's real total × the agent's configured rate via
  `AgentCommission::calculate()`. No caller can post an arbitrary figure. Duplicate commission for the
  same booking is rejected.
- **Payouts are transactional.** Creating a payout sweeps all approved unpaid commissions, links them,
  marks them paid, and decrements the agent balance **inside one DB transaction** — so the balance, the
  payout, and the commission rows can never disagree if something fails midway.
- **Performance** reports real figures: leads generated, bookings, booking value, average booking value,
  commission earned/paid/outstanding, current balance.

Bookings and leads gained a nullable `agent_id`, so agent-originated business is attributable. Agent KYC
documents reuse the Phase 2 polymorphic `Document` store (whitelist extended with `agent`).

## Verification

`php -l` passes with zero errors across `app/`, `bootstrap/`, `config/`, `database/`, `routes/`,
`tests/`, `resources/`; every controller referenced in `routes/api.php` exists on disk; no unguarded
duplicate `Schema::create` remains. `composer install` / `migrate` / `test` remain
`UNVERIFIED — REQUIRES LOCAL/STAGING EXECUTION`.
