# Phase 14 — AI Complaint Handling

**Date:** 2026-08-30
**Scope:** Directive §15 (Complaint AI), §28 Phase 14.

## The one thing that happens automatically, and why

§15 says: *"Critical issues must automatically escalate to humans."* That is the **only** automatic
action in this phase, and the design makes the asymmetry explicit:

- **Escalation only ever ADDS human attention.** It sets `escalated`, records
  `escalation_source: 'ai_critical'` and the reason, and notifies staff (in-app + Telegram). It
  deliberately **does not change the ticket's status** — a human decides whether the ticket is in
  progress, and the AI must not appear to be working it.
- If a ticket has no assignee, **every active user in the tenant** is notified, so a critical complaint
  cannot sit unseen because nobody happened to own it.
- Escalation is idempotent: an already-escalated ticket is not re-escalated or re-notified.

Failing to escalate a critical complaint is the costly error here; escalating one that turns out to be
routine costs someone a glance. The design is deliberately biased that way.

## Everything else is a suggestion

Triage output goes to a **separate `ticket_ai_triages` row**, never straight onto the ticket. Severity,
category, draft response, draft resolution, sentiment, and detected issues all sit there until a human
calls `POST .../ai-triage/{id}/apply`, which stamps `applied_by` and `applied_at`. So "the AI suggested"
and "a human acted on it" are permanently distinguishable in the data — which also means the escalation
audit trail is honest about what triggered it.

The prompt forbids the model promising **a refund, a price, a booking change, or a visa outcome** in its
draft reply — none of which it can authorise — and requires an `[AGENT: CONFIRM ...]` placeholder
instead. This is the same grounding rule applied in Phases 10 and 11.

`applyTriage()` maps AI severity onto the ticket's own priority vocabulary
(critical→urgent, high→high, medium→normal, else low) rather than writing a foreign value into the
column.

## Verification

`php -l` passes with zero errors across `app/`, `bootstrap/`, `config/`, `database/`, `routes/`,
`tests/`, `resources/`; every controller referenced in `routes/api.php` exists on disk. No AI call was
executed in this sandbox — triage behaviour is `UNVERIFIED — REQUIRES LOCAL/STAGING EXECUTION`.
