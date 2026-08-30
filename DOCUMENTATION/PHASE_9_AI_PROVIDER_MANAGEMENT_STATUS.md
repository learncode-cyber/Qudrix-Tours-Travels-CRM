# Phase 9 (Master Directive numbering) — AI Provider Management

**Date:** 2026-08-30
**Scope:** Directive §4 (AI-First Architecture / AI Provider Manager) + §28 Phase 9.

Builds on the Phase 0 skeleton (`AiProvider`, `AiUsageLog` models + `config/ai.php`), which had the
tables but no execution layer.

## Provider independence is enforced structurally, not by convention

The directive is explicit: *"It should be provider-independent... DO NOT hardcode a single provider."*
That is enforced here by architecture rather than discipline:

- `AiProviderAdapter` is the only contract application code sees.
- Three symmetric adapters implement it — `AnthropicAdapter`, `OpenAiAdapter`, `GeminiAdapter` — each
  encapsulating that vendor's own documented wire format (different auth headers, different role names,
  different usage field names). Adding a fourth vendor is one new class; no business logic changes.
- `AiGateway` is the single entry point. **No feature in this system ever names a vendor.** It asks the
  gateway for a completion; the gateway selects a provider.

All three adapters use Laravel's `Http` facade rather than a vendor SDK. This is deliberate: pulling in
one vendor's official SDK while the others go over raw HTTP would make the adapters asymmetric and quietly
privilege that vendor, against the directive's core requirement. (If Anthropic is later chosen as the
sole provider, `anthropic-ai/sdk` is a drop-in swap inside `AnthropicAdapter` with no change to anything
above it.)

## What `AiGateway` actually does

- **Selection** — active providers for the tenant, default first, then by `priority`.
- **Failover** — on any provider error it logs the failure and tries the next eligible provider; only
  when all fail does it throw, with every failure reason included in the message.
- **Spend limits** — per-provider `monthly_cost_limit_usd` and a global tenant ceiling from
  `config('ai.global_monthly_cost_ceiling_usd')`, both computed from **real logged usage this calendar
  month**, not estimates.
- **Usage + cost logging** — every call, success or failure, writes an `AiUsageLog` row with real token
  counts, latency, status, and error.

## Cost rates are operator-configured — deliberately not hardcoded

`ai_providers` gained `input_cost_per_million` / `output_cost_per_million`, entered by the operator.
Published model prices change and differ per contract, so baking a price table into code would make every
cost figure in the system silently wrong and unauditable — exactly what Directive §27 (Real Data Only)
prohibits. A provider with no rates entered records `cost_usd = 0` and is **flagged in the usage report
as `providers_without_cost_rates`**, so a zero can never be mistaken for "free".

For reference when entering Anthropic rates (per 1M tokens, verify against current published pricing):
Opus 5 $5 in / $25 out · Sonnet 5 $2 / $10 · Haiku 4.5 $1 / $5. Note that Anthropic model IDs are
complete as-is and must **not** carry a date suffix (`claude-opus-5`, never `claude-opus-5-20260101`).

## Credential safety (Directive §4: "API credentials must NEVER be exposed to frontend JavaScript")

Enforced at three layers: `credentials` is `encrypted:array` at rest; it is in the model's `$hidden` so it
cannot be serialized into any JSON response; and it is writable **only** through a dedicated
`PUT /ai-providers/{id}/credentials` endpoint that returns no credential data. A general `PATCH` on the
provider cannot touch it. There is no endpoint anywhere that returns a key.

`POST /ai-providers/{id}/test` issues a real minimal completion and records the actual outcome —
never a fabricated "connected".

## Verification

Same constraint as prior phases — no `vendor/`, no DB, no outbound network in this session, so **no live
call to any AI provider was made**. All three adapters are `UNVERIFIED — REQUIRES LOCAL/STAGING
EXECUTION`; the Anthropic adapter's wire format was written against the current documented Messages API
contract (`POST /v1/messages`, `x-api-key` + `anthropic-version: 2023-06-01`, required `max_tokens`,
polymorphic `content` blocks, `usage.input_tokens`/`output_tokens`), the others against their respective
documented contracts. `php -l` passes with zero errors across `app/`, `bootstrap/`, `config/`,
`database/`, `routes/`, `tests/`, `resources/`; every controller referenced in `routes/api.php` confirmed
to exist on disk.
