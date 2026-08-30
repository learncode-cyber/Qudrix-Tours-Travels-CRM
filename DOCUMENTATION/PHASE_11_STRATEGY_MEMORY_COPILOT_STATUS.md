# Phase 11 (Master Directive numbering) — Sales Strategies + Conversation Memory + AI Copilot

**Date:** 2026-08-30
**Scope:** Directive §8 (Sales Strategies), §9 (Conversation Memory), §13 (AI Sales Copilot), §28 Phase 11.

## Sales Strategy Manager (§8)

`SalesStrategy` holds the seven methodologies the directive names — consultative, SPIN, solution, value,
relationship, challenger, sandler. Each row carries **admin-editable `prompt_guidance` and `tone`**, a
`priority`, an active flag, and an optional `customer_segment_id` binding, which is exactly the
enable/disable + priority + tone + prompt + assign-by-segment control set §8 asks for. Because the
guidance text lives in the database rather than in code, a tenant can retune how the copilot sells
without a deploy.

## Conversation Memory (§9)

`CustomerMemory` stores **structured, typed entries** (category + key + value) rather than a free-text
blob, deliberately: §9 requires memory to be permission-controlled, editable, deletable, and auditable,
and none of those are practical against an opaque blob. Categories cover the fields the directive lists —
budget, travel preference, destination, group size, previous trips, preferred channel, objections,
requirements.

Two safeguards on top:

- **`is_sensitive` flag + `scopeSafeForAi()`.** An entry a human marks sensitive is excluded from every
  AI prompt. §9 says "do not store sensitive information unnecessarily"; by extension this system also
  does not *transmit* it unnecessarily — sensitive entries stay in the CRM for staff, and never reach a
  model.
- **Soft deletes + `created_by` + the existing `audit` middleware.** Every write is attributed and
  recoverable, satisfying the auditability requirement.

## AI Copilot (§13)

`AiCopilotService::assist()` composes its system prompt from the tenant's highest-priority active
strategy (falling back to a plain consultative approach when none is configured), and grounds it in the
lead's real record, real communications, and non-sensitive memory only.

It returns: suggested next question, objection handling, recommended product *categories*, upsell
opportunities, follow-up timing, sentiment, and a `facts_to_verify` list. The prompt forbids stating any
price, availability, booking confirmation, or visa rule — the same prohibition enforced in Phase 10 —
and every response is tagged `is_suggestion: true, human_in_control: true`, implementing §13's rule that
"AI suggestions must remain suggestions. Human agent remains in control."

`extractMemoryCandidates()` proposes memory entries from a real conversation but **writes nothing**: it
returns candidates with quoted evidence, a confidence value, and a `possibly_sensitive` flag, tagged
`requires_human_confirmation: true, stored: false`. A human confirms each one through
`POST /customer-memories`. Auto-writing extracted memory would have broken §9's permission-controlled
requirement, so it is deliberately not done.

## Verification

Same constraint as prior phases — no `vendor/`, no DB, no outbound network, so **no AI call was
executed**; copilot and extraction behaviour is `UNVERIFIED — REQUIRES LOCAL/STAGING EXECUTION`.
`php -l` passes with zero errors across `app/`, `bootstrap/`, `config/`, `database/`, `routes/`,
`tests/`, `resources/`; every controller referenced in `routes/api.php` exists on disk. 25 migrations now
in `database/migrations/`.
