# Phase 10 (Master Directive numbering) — AI Sales Agent + AI Package Builder

**Date:** 2026-08-30
**Scope:** Directive §5 (AI Sales Agent), §6 (AI Package Builder), §10 (Lead Scoring), §28 Phase 10.

Every AI call in this phase goes through the Phase 9 `AiGateway`, so no vendor is named anywhere in this
code and all usage/cost is logged automatically.

## The two prohibitions, enforced structurally

The directive is blunt about what AI must never do:

> AI must NOT fabricate: Flight availability · Hotel availability · Visa rules · Prices · Inventory ·
> Booking confirmation. **Never allow AI to invent inventory or pricing.** (§5, §6)

Prompt wording alone cannot guarantee that, so the guarantee is built into the data flow instead:

**1. The model is only ever shown real rows.** `AiPackageBuilderService::availableInventory()` queries
this tenant's actual `Flight` / `HotelRoomType` / `Transport` rows, filtered to genuinely available
capacity, and that catalogue is the *only* inventory the model ever sees. It has nothing to hallucinate
*from*.

**2. Everything the model names is re-verified.** The new `InventoryResolver` service is the single
choke point that turns a requested component into a real, tenant-owned, in-stock row. If the model
invents a `reference_id`, resolution throws and the API returns **422 with the verification failure**
rather than a plausible-looking package. The controller surfaces that plainly.

**3. The model never sets a price.** Cost is summed from real unit prices by the resolver; the final
figure comes from the deterministic Phase 6 `PricingEngine`, with its full audit log. The proposal prompt
explicitly forbids stating any price in its own summary.

**4. Nothing is auto-applied.** Package proposals come back with `requires_human_approval: true`; reply
drafts come back `is_draft: true, sent: false`. Nothing is sent or booked by the model.

`PackageBuilderController` (Phase 6) was refactored onto the same `InventoryResolver`, so the
deterministic and AI-assisted builders now share one verification path — they cannot drift apart.

## AI Sales Agent (§5, §10)

`AiSalesAgentService` is grounded the same way: the only context any prompt receives is the lead's real
record plus its real `Communication`, `Quotation`, and `Booking` rows for that tenant.

- **`qualifyLead`** — returns score (0-100), buying intent, signals, recommended next action, detected
  objections, and missing information. The score is persisted as a `LeadScore` row with
  `score_type: 'ai_suggested'` and full reasoning metadata — **never overwriting a human's scoring**, and
  the response is explicitly tagged `is_suggestion: true, human_can_override: true` per §10's requirement
  that staff can override AI scores.
- **`summarizeConversation`** — returns a handover summary, requirements, open questions, commitments,
  sentiment. Returns a plain "no communications to summarize" message rather than inventing a summary
  when the history is empty.
- **`suggestReply`** — drafts a reply **for the rep to edit and send**. The prompt forbids stating any
  price, availability, booking confirmation, or visa rule, and instead requires a `[CONFIRM PRICE]`-style
  placeholder plus a `facts_to_verify_before_sending` list. This directly implements §13's rule that
  "AI suggestions must remain suggestions. Human agent remains in control."

## Robustness detail

Models occasionally wrap JSON in prose or code fences despite instructions. Both services recover the
object with a fallback regex extract before failing, so a cosmetic formatting slip doesn't break a
customer-facing workflow. A genuinely unparseable response raises `AiProviderException` with the
truncated text, rather than returning empty data that looks like a valid answer.

## Verification

Same constraint as prior phases — no `vendor/`, no DB, no outbound network in this session, so **no AI
call was executed**. All AI behaviour is `UNVERIFIED — REQUIRES LOCAL/STAGING EXECUTION`. What *is*
verified: `php -l` passes with zero errors across `app/`, `bootstrap/`, `config/`, `database/`, `routes/`,
`tests/`, `resources/`; every controller referenced in `routes/api.php` exists on disk; and the
grounding/verification path is structural (a hallucinated component cannot reach a quotation because
`InventoryResolver` fails closed), which is reviewable by reading the code rather than requiring a live
model to demonstrate.
