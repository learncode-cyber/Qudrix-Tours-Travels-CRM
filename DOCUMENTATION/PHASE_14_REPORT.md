# Phase 14 Report — Complaint Handling + Automation

Like Phases 6, 8, 9, 10, 12, and 13, this module's backend
(`SupportTicketController`, `AiComplaintController`/`AiComplaintService`,
the legacy `ComplaintController`, `AutomationController`/`AutomationEngine`,
`AutomationTemplateController`, `AutomationLogController`,
`AutomationDashboardController`) was built in a prior session but had
never actually been executed against a live database, called with a real
HTTP request, or covered by an automated test. This phase's work was:
(1) audit it, (2) live-test every endpoint end-to-end, (3) write its
automated test suite, (4) build its frontend, (5) verify everything
together. **Two real bugs found and fixed** this phase — the first phase
since Phase 11 where the audit turned up an actual defect.

## 1. What was implemented

- **Audit**: route-to-controller-method cross-reference (clean), plus a
  full read of both the AI triage service and the automation engine. Two
  bugs surfaced from reading the code against its own migrations rather
  than from a failing request — see §8/§9.
  - The AI triage design mirrors the Phase 10/11 "suggestion only"
    pattern: severity/category/draft reply/resolution are written to a
    separate `ticket_ai_triages` row, never onto the ticket itself,
    until a human explicitly applies them. The one directive-mandated
    automatic action — a `critical` severity auto-escalates — only ever
    *adds* human attention (flips `escalated`, notifies the assignee or
    every active tenant user) and deliberately never touches `status`,
    never answers, never resolves.
  - The automation engine executes real actions (`create_task`,
    `update_customer` rejecting non-fillable fields, real `Mail::raw`,
    a real webhook POST) and honestly refuses to invent an SMS
    integration (`send_sms` returns `CONTRACT REQUIRED: no SMS provider
    is configured`) — consistent with the Phase 7/8 pattern of never
    fabricating a provider without a supplied contract.
- **Live backend verification**: created a support ticket, ran AI triage
  against this sandbox's real (invalid-key) Anthropic provider and
  confirmed the honest `502` with the real Anthropic error text
  propagated through unchanged, updated status, posted a reply,
  escalated manually. Created an automation, attached a `create_task`
  step via the model layer (no API creates steps directly — see Known
  Limitations), executed it and watched a real `Task` row appear, and
  confirmed the dashboard summary/metrics reflected the real run.
  Separately proved the new SSRF guard live: a webhook step pointed at
  `127.0.0.1:9999` was rejected with the exact "resolves to a private or
  reserved address" reason instead of being attempted.
- **Frontend** (new, first UI this module has ever had):
  - `SupportTicketsPage.tsx` — list + create.
  - `SupportTicketDetailPage.tsx` — status workflow, replies, escalation
    banner, and an AI Triage panel that runs triage, lists triage
    history, and lets a human apply a suggestion — with the same
    "suggestion, not automatic" framing the backend actually implements.
  - `AutomationsPage.tsx` — list + dashboard summary stat cards + create.
  - `AutomationDetailPage.tsx` — steps table, stats, Test (preview) vs.
    Execute (real run) actions with the JSON result shown, and execution
    logs with clear-logs.
- **Automated tests**: `tests/Feature/Phase14ComplaintAutomationTest.php`
  — 19 tests, the first this module has ever had.

## 2. Files / modules changed

Backend:
- `tests/Feature/Phase14ComplaintAutomationTest.php` (new)
- `app/Models/SupportTicket.php` — added `escalation_source`,
  `escalation_note` to `$fillable` (bug fix, see §8)
- `app/Services/AutomationEngine.php` — added
  `guardAgainstPrivateNetwork()` to `callWebhook()` (bug fix, see §8)

Frontend:
- `frontend/src/pages/SupportTicketsPage.tsx` (new)
- `frontend/src/pages/SupportTicketDetailPage.tsx` (new)
- `frontend/src/pages/AutomationsPage.tsx` (new)
- `frontend/src/pages/AutomationDetailPage.tsx` (new)
- `frontend/src/api/endpoints.ts` — added wrappers for support tickets,
  AI triage, legacy complaints, and the full automation surface
- `frontend/src/types/index.ts` — added `SupportTicket`,
  `SupportTicketReply`, `TicketAiTriage`, `TriageResult`, `Complaint`,
  `Automation`, `AutomationStepData`, `AutomationLogData`,
  `AutomationStats`, `AutomationDashboardSummary`,
  `AutomationDashboardMetrics`, `AutomationTemplateData`
- `frontend/src/App.tsx` — added `/support-tickets`,
  `/support-tickets/:id`, `/automations`, `/automations/:id` routes
- `frontend/src/components/AppLayout.tsx` — added "Support Tickets" and
  "Automations" nav entries

Documentation:
- `DOCUMENTATION/PHASE_14_REPORT.md` (this file)
- `DOCUMENTATION/API_DOCUMENTATION_COMPLETE.md` — Phase 14 addendum
- `CHANGELOG.md`, `PROJECT_STATUS.md` — updated

## 3. Database changes

None. `support_tickets`, `support_ticket_replies`, `ticket_ai_triages`,
`complaints`, `automations`, `automation_steps`, `automation_logs`,
`automation_templates` all already existed from prior-session migrations,
including the `escalation_source`/`escalation_note` columns fixed in §2 —
those columns already existed; only the model's `$fillable` was missing
them.

## 4. API changes

None — every route already existed. Two behavior corrections (not new
routes) landed this phase: critical-triage escalation now actually
persists its source/note, and a webhook step now refuses to reach a
private network address. See
`DOCUMENTATION/API_DOCUMENTATION_COMPLETE.md`'s Phase 14 addendum for the
full endpoint reference.

## 5. Frontend changes

Four new pages and their routes/nav entries, described in §1. Reused
every existing shared component (`Badge`, `Modal`, `Loading`,
`ErrorBanner`, `EmptyState`, `NotAvailable`, `StatCard`) and utility
(`formatDate`, `getErrorMessage`, `statusTone`, `titleCase`) rather than
introducing new ones.

## 6 & 7. Tests performed and results

**Automated (PASS):**
```
Tests:    255 passed (839 assertions)
Duration: 11.36s
```
(236 pre-existing + 19 new `Phase14ComplaintAutomationTest` tests, zero
failures, zero regressions.) The 19 new tests cover: support ticket CRUD
+ status validation + reply + escalate; legacy complaint CRUD and
`resolution_date` stamping; AI triage's honest-failure paths (no active
provider, a real provider 401 propagated through with nothing persisted);
a non-critical triage stored purely as a suggestion that never touches
the ticket's own priority/status; a critical triage that auto-escalates
**and correctly persists `escalation_source`/`escalation_note`** — this
test would have failed before the §8 fix, since those fields were
silently dropped; triage history + apply (mapping severity onto the
ticket's priority vocabulary) + apply-twice rejection; tenant scoping on
triage; automation CRUD + trigger-type validation; step execution
(`create_task` creating a real `Task` row and logging success,
condition-based skip, the honest `send_sms` message, and — the other bug
this phase found — a webhook step targeting `127.0.0.1` being blocked);
the test-vs-execute distinction (test never logs); tenant scoping on
automations; logs/stats/clear-logs; the dashboard summary and metrics
(100% success rate on a clean run); and automation template
index/show/by-category/use with real `usage_count` incrementing.

**Manual/live (PASS):** Direct curl calls with a real JWT against a live
`php artisan serve` instance on this sandbox's accumulated dev database
(which has a real, invalid-key Anthropic provider configured from earlier
phases): created a ticket, ran AI triage and got a real `502` with
Anthropic's actual `authentication_error` response text passed through
unchanged, created an automation and confirmed `execute` on a
step-free automation returns `{}` cleanly, then (after attaching a
webhook step via the model layer, since no API creates steps — see §12)
confirmed the new SSRF guard rejects `http://127.0.0.1:9999/hook` live
with the exact reason string.

Headless-Chromium Playwright E2E against the built frontend served via
`vite preview`, logged in as the real seeded admin: created a support
ticket, ran AI triage and confirmed the honest failure banner rendered,
updated ticket status, created an automation, and executed it with the
JSON result rendered — zero `pageerror` events. A separate full-app
sweep (all pre-existing pages across Phases 2–13) also completed with
zero page errors, confirming no regression.

**Build/typecheck (PASS):** `npx tsc --noEmit` silent; `npm run build`
succeeded (135 modules, no errors).

## 8 & 9. Bugs discovered and fixed

**Two bugs found — the first phase since Phase 11 with an actual defect.**

1. **`SupportTicket::$fillable` was missing `escalation_source` and
   `escalation_note`.** Both columns exist on the `support_tickets`
   table (added by this same feature's own migration,
   `2026_08_30_200000_create_phase14_complaint_ai_tables.php`), and
   `AiComplaintService::escalateToHumans()` explicitly passes both to
   `$ticket->update([...])` on every critical-severity auto-escalation —
   but because they weren't in `$fillable`, Eloquent silently dropped
   them. The ticket's `escalated`/`escalated_at` flags still flipped
   correctly (those *are* fillable), but the audit trail of *why* it
   escalated and that the source was `ai_critical` rather than a human
   was silently lost on every occurrence. This matches the exact
   recurring bug class from Phases 4, 5, 7, and 11: a column that exists
   on both the migration and gets written to in application code, but is
   missing from one specific model/controller's fillable/validation
   whitelist. **Fixed** by adding both fields to `$fillable`; a new test
   (`test_critical_triage_auto_escalates_and_persists_escalation_source_and_note`)
   asserts both fields on the refreshed ticket and would fail on the
   pre-fix code.
2. **`AutomationEngine::callWebhook()` had no SSRF guard.** Phase 8 built
   `ApiConnectorService::guardAgainstPrivateNetwork()` specifically
   because "operator-supplied URLs are attacker-reachable configuration
   in a multi-tenant system" — a tenant admin could otherwise point a
   connector at `127.0.0.1` or a cloud metadata endpoint and use the CRM
   as an SSRF proxy into the host's own network. The automation engine's
   `webhook` action step is exactly the same shape of tenant-configured,
   attacker-reachable URL, reachable via `POST /automations/{id}/execute`
   — but it called `Http::post($url, ...)` directly with no such check.
   **Fixed** by adding the identical guard (same
   `ALLOW_PRIVATE_NETWORK_CONNECTORS` override) to
   `AutomationEngine::callWebhook()`; verified live and by a new test
   (`test_automation_webhook_step_is_blocked_from_reaching_a_private_network_address`)
   that a step targeting `127.0.0.1` is now rejected with an honest
   `error` result instead of being attempted.

Both were found by reading the code against its own migrations/sibling
services rather than by a failing request — the kind of defect static
syntax checking and even a naive live test wouldn't surface, since the
"happy path" (escalation flag flips; webhook to a real public URL
succeeds) looked correct on the surface.

## 10. Regression results

Full suite: 255/255 passing (was 236/236 before this phase — net +19,
zero failures introduced). Full-app Playwright sweep across all
pre-existing pages plus this phase's four new pages: zero `pageerror`
events. No existing behavior changed except the two fixes in §8, both of
which only add previously-missing protection/data — no existing
passing test or verified flow was altered.

## 11. Security considerations

- **New this phase**: automation webhook steps are now protected against
  SSRF the same way Integration Manager connectors already were (§8,
  finding 2) — a real, previously-unmitigated vector in a
  multi-tenant system where automation configuration (including webhook
  URLs) is operator-controlled.
- Every query in this phase's controllers is tenant-scoped by
  `where('tenant_id', ...)` — confirmed by this phase's tenant-scoping
  tests on both `support-tickets`/`ai-triage` and `automations`.
- The AI triage prompt is built only from the ticket's own subject,
  description, category, priority, status, and non-internal replies —
  internal notes (`is_internal_note: true`) are explicitly excluded from
  what's sent to the model.
- The AI is instructed never to promise a refund, price, booking change,
  or visa outcome — a policy constraint enforced at the prompt level and
  matching the pattern of every prior AI feature (nothing the model says
  is auto-applied without a human review step, and even then only
  severity/category are copied — the draft response is never
  auto-sent).
- No new secrets, API keys, or credentials introduced.

## 12. Known limitations

- **No API creates `AutomationStep` rows directly.** Steps can only be
  attached via `automation-templates/{id}/use` (which just returns the
  template's `workflow_config` for the caller to act on) or directly at
  the model layer. This phase's automation detail page shows and runs
  existing steps but has no "add a step" UI, matching what the backend
  actually exposes — building a step editor is future scope, not
  invented here.
- **No auto-firing of automations by their own `trigger_type`.** Despite
  `booking_created`/`customer_added`/etc. existing as an enum, nothing in
  the codebase currently dispatches an automation automatically when
  that event occurs — `execute` is only ever called explicitly (this
  phase's frontend button, or a future caller). This is an existing gap
  from the prior session's build, not something this phase's audit was
  scoped to close; flagging it plainly rather than silently.
- Same cross-cutting limitations as every prior phase (RBAC not
  route-enforced, MySQL-only in production/SQLite-only verified here) —
  see `PROJECT_STATUS.md`.

## 13. UNVERIFIED items

- Production MySQL 8.0+ behavior — verified here against SQLite only.
- RBAC-level authorization on these endpoints — same cross-cutting,
  already-documented gap from Phase 1, not new to this phase.
- A **successful** AI triage completion (a real 200 with a genuine
  model-generated triage) remains UNVERIFIED — this sandbox only has a
  deliberately invalid Anthropic key, so only the honest-failure path was
  exercised live; the mocked-success path was verified by the automated
  test suite's `Http::fake()` calls, which prove the code correctly
  parses and stores a well-formed response, not that a real model
  produces one.
- Real email delivery for the `send_email` automation action — no
  outbound network/mail transport in this sandbox; `Mail::raw()`'s error
  handling path was exercised, not a real send.

## 14. Deployment instructions

Identical to every prior phase — no new migrations, no new environment
variables:
```
composer install --no-dev --optimize-autoloader
# configure .env: DB_CONNECTION, DB_DATABASE (MySQL 8.0+ in production), etc.
php artisan key:generate --force
php artisan jwt:secret --force
php artisan migrate --force
php artisan db:seed --force        # capture the printed admin password
php artisan serve                  # or your real web server

# frontend
npm install
# set VITE_API_BASE_URL in frontend/.env to the backend's real URL
npm run build
# serve dist/ via your static host / reverse proxy
```

## 15. Verification commands + expected results

```bash
php artisan test --filter=Phase14ComplaintAutomationTest
# expect: Tests: 19 passed (96 assertions)

php artisan test
# expect: Tests: 255 passed (839 assertions)

# live curl (after login, with a Bearer token, a critical AI provider response):
curl -X POST /api/v1/support-tickets/<id>/ai-triage
# expect on critical severity: ticket.escalated=true, escalation_source="ai_critical", escalation_note set

# webhook SSRF guard (after attaching a webhook step targeting a private address):
curl -X POST /api/v1/automations/<id>/execute
# expect: {"data":{"1":{"type":"webhook","status":"error","reason":"...private or reserved address..."}}}

cd frontend && npx tsc --noEmit && npm run build
# expect: build succeeds, typecheck silent
```
