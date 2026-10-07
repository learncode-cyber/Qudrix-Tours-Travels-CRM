# Batch 18 Report (2026-10-06)

## Fixed
1. **Cross-tenant IDOR in `AIProviderController@setFeatureConfig`** — `ai_provider_id` was validated with a bare `exists:ai_providers,id`, so a tenant could bind a feature to another tenant's provider (their API key and billing). Now validated with `Rule::exists(...)->where('tenant_id', caller)`.
2. **Automation action types did not match the engine** — the UI offered `send_whatsapp`, `update_status`, `notify_staff`, `webhook_call`, which `AutomationEngine::executeAction()` answers with "Unknown action type". UI now offers exactly the engine's types: send_email, send_sms, create_task, update_customer, create_notification, webhook, delay.
3. **AutomationEngine crashed on missing config** — null `action_config` hit an `array` type hint (TypeError) and every action read config keys without defaults. Now `?? []` / `?? null`; `checkCondition` tolerates incomplete condition_config.
4. **AI provider cost fields unreachable** — columns and validation existed but `index()` did not select them and `store()` ignored them. Both fixed; UI has create-time fields and an inline "Edit costs" row; usage-stats `by_feature` is now displayed.
5. **Automation step config + reorder** — per-action config inputs (keys match what the engine reads) and Up/Down reorder via `PUT .../steps/{id}` swapping `step_order`.
6. **BookingDetail refactor** — 684 lines -> 77-line shell + `types.ts` + `sections/{TravelersMahram,Installment,Hotel,Flight,Transport}Section.tsx`. Logic moved unchanged except `load()` -> `onChanged()` and `id` -> `bookingId`.

## Found, NOT fixed (needs your decision)
- Every `AutomationEngine` action is a stub: it returns `sent: true` / `created: true` without sending an email/SMS, creating a task, or calling the webhook URL. Test/execute results therefore look successful but do nothing. Wiring real delivery needs the Communication phase services and real credentials.
- `delay_seconds` uses blocking `sleep()` inside the HTTP request; long delays will time out. Needs queued jobs.
- Step reorder sends two parallel PUTs (Promise.all), not a transaction; a failure between them could leave duplicate `step_order` values.

## Verification
- `tsc` syntax/identifier/unused-locals check on touched files (without node_modules, so React types are absent): no syntax errors, no undefined names, no unused imports (4 unused imports were caught and removed). Full `npm run build` NOT run.
- PHP NOT linted or executed (no PHP in sandbox). PHP edits were re-read by eye; one of my own automated replaces broke `checkCondition` mid-batch and was repaired.
- Nothing runtime-verified. STATUS: IMPLEMENTED, UNVERIFIED.
