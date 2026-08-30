# Phase 17 — Marketing Tools + Unified Conversations

**Date:** 2026-08-30
**Scope:** Directive §3.P (Marketing), §3.N (Conversations), §28. Closes the last two large modules the
competitor benchmark has that Qudrix was missing (Marketing Tools: 16 screens — their largest module;
Conversations: 5 screens).

## Marketing (§3.P)

**Contact lists** — static (explicit members) or dynamic (criteria-defined), holding customers and/or
leads.

**Campaigns** across email / SMS / WhatsApp, with one `campaign_recipients` row per recipient carrying
its **real delivery outcome**. This is the whole point of the design: the campaign report counts what
actually happened, not what was intended.

Three honesty guarantees, all of which the directive's §27/§37 rules demand:

1. **A channel with no configured transport does not fake success.** Email uses Laravel's configured
   mailer. SMS and WhatsApp require an operator-configured connector from the Phase 8 Integration
   Manager; if none exists with a mapped `send` endpoint, every recipient is recorded as `skipped` with
   a `CONTRACT REQUIRED` reason.
2. **Contacts with no usable destination are recorded as `skipped`**, with the reason, at prepare time —
   so a list of 500 where only 300 have emails reports 300 sends and 200 explained skips, not "500 sent".
3. **A run where nothing was delivered is marked `failed`, not `sent`.**

**Coupons** with server-side validation of every rejection reason (inactive, not-yet-valid, expired,
usage limit reached, below minimum). Two deliberate safeguards:
- The discount is **always derived** by `Coupon::discountFor()` and **capped at the booking amount** — a
  100%-plus discount cannot produce a negative total.
- Redemption runs inside a transaction with `lockForUpdate()` on the coupon row, so concurrent requests
  cannot push `used_count` past `usage_limit`.
- Redemption deliberately **does not mutate the booking total**; it records the discount and tells the
  caller to apply it through the quotation/invoice, where the change stays auditable.

## Unified Conversations (§3.N)

One thread per customer per channel (`website_chat`, `email`, `whatsapp`, `telegram`, `sms`, `internal`)
with a real message history, assignment, status, and unread tracking.

Outbound delivery is honest per channel — this is the same rule as campaigns, applied per message:
- **email** → Laravel's mailer, marked `sent` only if it dispatched.
- **telegram** → the Phase 7 bot client, recording `sent` or `failed` with the API's real reason.
- **internal** → `not_attempted` ("Internal channel — nothing is sent").
- **whatsapp / sms / website_chat** → require an operator connector; without one the message is stored
  with `delivery_status: 'not_attempted'` and a `CONTRACT REQUIRED` explanation.

**No message is ever marked `sent` unless a transport actually accepted it.** Internal notes are stored
but never transmitted.

## Project size after this phase

79 controllers · 127 models · 31 migrations · 327 routes.

## Verification

`php -l` passes with zero errors across `app/`, `bootstrap/`, `config/`, `database/`, `routes/`,
`tests/`, `resources/`; every controller referenced in `routes/api.php` exists on disk. Actual email /
Telegram / connector delivery is `UNVERIFIED — REQUIRES LOCAL/STAGING EXECUTION` (no outbound network in
this sandbox), as are `composer install` / `migrate` / `test`.
