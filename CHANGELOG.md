# Changelog

All notable changes to the Qudrix Travel CRM/ERP project, following the
master development directive's phase numbering (Phase 0 = Foundation,
Phase 1 = Backend Foundation + Auth + RBAC, Phase 2 = Complete CRM, ...).

## [Unreleased] — Master Directive Phase 14: Complaint Handling + Automation

Backend (`SupportTicketController`, `AiComplaintController`/
`AiComplaintService`, legacy `ComplaintController`, `AutomationController`/
`AutomationEngine`, `AutomationTemplateController`, `AutomationLogController`,
`AutomationDashboardController`) already existed from a prior session.
Live-tested end to end for the first time and wrote its first automated
coverage: 19 new tests. Two real bugs found and fixed this phase.

### Added
- Frontend: a Support Tickets page (list + create) and a ticket detail
  page (status workflow, replies, escalate, and an AI Triage panel that
  runs triage, shows the suggestion history, and lets a human apply a
  suggestion to the ticket — with an explicit "suggestion, not an
  automatic action" note matching the backend's actual behavior). An
  Automations page (list + dashboard summary) and an automation detail
  page (steps table, Test/Execute actions, execution result, execution
  logs with clear-logs, and stats).
- `tests/Feature/Phase14ComplaintAutomationTest.php` — 19 tests covering
  support ticket CRUD/status/reply/escalate, legacy complaint CRUD, AI
  triage's honest-failure path (no provider, real provider 401),
  non-critical triage as a pure suggestion, critical-triage
  auto-escalation (including that `escalation_source`/`escalation_note`
  are actually persisted — see Fixed below), triage history + apply +
  apply-twice rejection, tenant scoping, automation CRUD + trigger-type
  validation, step execution (`create_task`, condition-skip, the honest
  `send_sms` "no provider configured" message, and the new webhook SSRF
  block), the test-vs-execute distinction, automation logs/stats/clear,
  the automation dashboard, and automation templates.

### Fixed
- **`SupportTicket` was missing `escalation_source` and
  `escalation_note` from its `$fillable` array**, even though both
  columns exist on the table (added in this same feature's own
  migration). Every AI critical-severity auto-escalation was silently
  dropping the audit trail of *why* it escalated and that the source was
  `ai_critical` — the ticket's `escalated`/`escalated_at` flipped, but
  the note explaining the escalation vanished. Added both fields to
  `$fillable`; verified live that a critical triage now persists both.
- **`AutomationEngine::callWebhook()` had no SSRF guard**, unlike the
  functionally identical `ApiConnectorService::guardAgainstPrivateNetwork()`
  already enforced on Integration Manager connectors. A tenant-configured
  automation webhook step could be pointed at `127.0.0.1` or any other
  private/reserved address and used as an SSRF proxy into the host's own
  network. Added the same guard (respecting the existing
  `ALLOW_PRIVATE_NETWORK_CONNECTORS` override) to the webhook step;
  verified live that a step targeting `127.0.0.1` is now rejected with an
  honest reason instead of being attempted.

## [Unreleased] — Master Directive Phase 13: Upsell/Cross-sell Engine + Sales Script A/B Testing

Backend (`UpsellController`/`UpsellEngine`, `AbTestingController`/
`AbTestingService`) already existed from a prior session. Live-tested
end to end for the first time and wrote its first automated coverage.
Zero bugs found, matching Phase 6, 8, 9, 10, and 12.

### Added
- Frontend: an Upsell page (Rules tab — CRUD with the real trigger/
  recommend-type enums from the API, activate/deactivate; Effectiveness
  tab — real shown/accepted/acceptance-rate/revenue per type) and an
  A/B Testing page (experiment list + create, a detail page for
  managing variants, starting/stopping, assigning a lead, and viewing
  results — including the honest "sample too small to call a winner"
  message rendered exactly as the backend reports it). No such UI
  existed anywhere before this phase.
- `tests/Feature/Phase13UpsellAbTestingTest.php` (16 tests) — the first
  automated coverage this module has ever had: a recommendation is
  never shown for a component the booking already has; an availability-
  gated recommendation is silently skipped when nothing is actually
  available and shown with a real inventory count when it is; a non-
  inventory recommend type carries its honest "not tracked as
  inventory" note; real component detection from the `flight_bookings`
  join table; recording a shown recommendation through to its outcome
  and effectiveness stats; the "fewer than 2 variants" start guard; the
  variant-upsert-by-label idempotency; assignment being refused on a
  non-running experiment and being deterministic/idempotent for the
  same lead; response/conversion recording; and the results endpoint's
  statistical honesty in both directions — declining to name a winner
  under the 30-assignment-per-variant threshold, and correctly naming
  one once a real, well-powered sample shows a clear margin.

### Fixed
Nothing — no bugs were found in this module.

## [Unreleased] — Master Directive Phase 12: Analytics + Behavioral Intelligence

Backend (`AnalyticsDashboardController`/`BehavioralAnalyticsService`)
already existed from a prior session — a pure real-SQL-aggregation
module with no AI or external dependency. Live-tested end to end with
controlled fixtures for the first time and wrote its first automated
coverage. Zero bugs found, matching Phase 6, 8, 9, and 10.

### Added
- Frontend: an Analytics page (Executive tab — revenue, leads, operations
  across every travel-ops module, revenue trend, lead-source and staff
  performance, P&L, all as real numbers with an explicit note whenever a
  metric is genuinely unavailable rather than shown as a misleading
  zero; Behavioral tab — time-to-conversion, deal value, follow-up
  effectiveness, per-channel engagement, customer base; Quotation
  Funnel tab). No such UI existed anywhere before this phase.
- `tests/Feature/Phase12AnalyticsBehavioralTest.php` (11 tests) — the
  first automated coverage this module has ever had, verified against
  controlled fixtures rather than eyeballed numbers: real revenue
  correctly excludes a `pending` payment while including a `completed`
  one; `conversion_rate_percent` and other averages are `null` (not
  `0`) when there's no data to compute them, with the executive
  dashboard's `unavailable_metrics` note asserted directly; tenant
  scoping; real `GROUP BY` pipeline totals; revenue-trend's gap-filling
  (every requested month present, zero-revenue months explicit); a
  lead-to-booking time-to-conversion computed to the exact day (6 days,
  from controlled timestamps); per-channel read-rate math; quotation
  funnel totals; and P&L correctly netting real income against a real
  expense with the right margin percentage.

### Fixed
Nothing — no bugs were found in this module.

## [Unreleased] — Master Directive Phase 11: Sales Strategies + Customer Memory + AI Copilot

Backend (`SalesStrategyController`, `CustomerMemoryController`,
`AiCopilotController`/`Service`) already existed from a prior session,
built on the Phase 9 gateway. Live-tested end to end for the first time
— including real round trips to Anthropic's live API for the Copilot —
and found one real bug in an unrelated-but-blocking spot.

### Added
- Frontend: a Sales Strategies page (CRUD, activate/deactivate); two new
  tabs on the existing per-lead AI Assistant modal (Copilot — live
  suggestions grounded in the lead's active strategy, non-sensitive
  memory, and recent communications; Extract Memory — AI-suggested
  memory candidates a human reviews and confirms one at a time before
  anything is actually saved). No such UI existed anywhere before this
  phase.
- `tests/Feature/Phase11SalesStrategyCopilotTest.php` (11 tests) — the
  first automated coverage this module has ever had: sales strategy CRUD
  + key/tenant validation, customer memory CRUD + category validation +
  the customer-or-lead requirement, the `LeadController` fix below (as a
  regression test), Copilot honest failure with no active provider, the
  extract-memory zero-HTTP-call short-circuit, the Copilot correctly
  picking up the active strategy, **a live assertion (via
  `Http::assertSent`) that a sensitive memory value never reaches the
  outgoing prompt while a non-sensitive one does**, and extract-memory
  returning unconfirmed candidates that are never auto-written to
  `customer_memories`.

### Fixed
- `PUT /api/v1/leads/{id}` silently dropped `customer_id` — present on
  `Lead::$fillable` since Phase 2 specifically so a lead could be linked
  back to the customer it originated from, but missing from
  `LeadController::update()`'s validation whitelist. There was no way
  through the API to link an existing lead to a customer after creation.
  Same bug class as Phase 4's `embassy_id` and Phase 7's
  `external_thread_id`. Found live-testing this phase's Copilot feature
  (which needs a lead linked to a customer to see real communications),
  fixed by adding the field to the validation.

## [Unreleased] — Master Directive Phase 10: AI Sales Agent + AI Package Builder

Backend (`AiSalesAgentController`/`Service`,
`AiPackageBuilderController`/`Service`) already existed from a prior
session, built entirely on the Phase 9 gateway and the Phase 6 pricing/
inventory engine. Live-tested end to end for the first time — including
a real round trip to Anthropic's live API — and wrote its first
automated coverage. Zero bugs found, matching Phase 6, 8, and 9.

### Added
- Frontend: an "AI Assistant" action on every lead (Leads page, both
  kanban and list views) opening a modal with Qualify / Summarize /
  Suggest Reply tabs — every result rendered as an explicit suggestion
  or draft, never applied automatically. An AI Package Assistant page
  (free-text → structured requirements → a proposal built only from
  real, currently-available inventory, re-verified and priced
  deterministically, shown as a draft with an explicit "nothing has
  been booked" notice). No such UI existed anywhere before this phase.
- `tests/Feature/Phase10AiSalesAgentTest.php` (10 tests) — the first
  automated coverage this module has ever had: honest failure with no
  active provider and on real provider failure (via `Http::fake`);
  `summarize`'s short-circuit making zero HTTP calls when a lead has no
  communications; `qualify`'s AI-suggested score being persisted and
  visible without overwriting anything; a live assertion that only real
  grounded data (the lead's actual name and message text) reaches the
  prompt payload; `suggest-reply` always returning `is_draft: true,
  sent: false`; `interpret` extracting structured requirements;
  `propose`'s zero-HTTP-call short-circuit when no inventory matches;
  `propose` correctly verifying an AI-named component against real
  inventory and pricing it deterministically; and `propose` rejecting a
  hallucinated component id with a `422`.

### Fixed
Nothing — no bugs were found in this module.

## [Unreleased] — Master Directive Phase 9: AI Provider Management

Backend (`AiProviderController`, `AiGateway`, and the Anthropic/OpenAI/
Gemini adapters) already existed from a prior session but had never been
executed live or covered by a test. Live-tested end to end (including a
real request against Anthropic's live API — reachable from this sandbox
— that correctly and honestly failed on an intentionally invalid key)
and wrote its first automated coverage. Zero bugs found, matching
Phase 6 and Phase 8.

### Added
- Frontend: an AI Providers page (Providers tab — create, set/rotate an
  API key through a write-only form, activate/deactivate, set default,
  run a real connection test and see its honest result inline, delete;
  Usage tab — real aggregated cost/token/call stats per feature since a
  given date, with providers missing cost rates explicitly flagged
  rather than silently costed at $0). No such UI existed anywhere
  before this phase, despite the backend having existed since a prior
  session.
- `tests/Feature/Phase9AiProviderTest.php` (15 tests) — the first
  automated coverage this module has ever had: credential hiding across
  every read path, tenant scoping, the single-default-per-tenant
  invariant, the activation-refused-without-credentials guard, honest
  test-connection success/failure (via `Http::fake`, no real network
  required for determinism), the gateway's failover to a second
  provider on the first one's failure (with both attempts correctly
  logged), the "no provider configured" and "over spend limit" failure
  paths, real cost computed from configured per-token rates on live
  token counts, the zero-cost-when-unrated path and its usage-endpoint
  flag, and usage aggregation being correctly tenant-scoped.

### Fixed
Nothing — no bugs were found in this module.

## [Unreleased] — Master Directive Phase 8: CRM External API Integration

Architecture-only per the master directive's own rule for this phase: no
external provider contract was ever supplied, so nothing built here talks
to a real third party — a generic, operator-configurable connector engine
(`ApiConnectorController`, `ApiConnectorService`) already existed from a
prior session. This phase live-tested it end to end for the first time
(against a local mock server, never a real provider) and built its
frontend. Like Phase 6, zero bugs were found.

### Added
- Frontend: an Integrations page (list connectors by category, create
  one) and a connector detail page (a write-only credentials form that
  never echoes back what was saved, toggle active/inactive, "Test
  Connection", endpoint-mapping CRUD with request/query/response-mapping
  JSON editors, a "Try an Operation" ad-hoc executor, and a call-log
  tab). No such UI existed anywhere before this phase.
- `tests/Feature/Phase8ApiConnectorTest.php` (13 tests) — the first
  automated coverage this module has ever had: credentials never leak
  through any read path, tenant scoping, the activation-refused-without-
  a-mapped-endpoint guard, endpoint mapping CRUD flipping
  `contract_required`, the SSRF guard blocking a private-network target,
  `execute` correctly rejecting an unmapped operation and an inactive
  connector, a full execute round-trip (via `Http::fake`) proving real
  credential substitution into the outgoing request and correct response
  mapping, honest failure recording on a non-2xx provider response, and
  `test-connection` recording the real outcome on the connector.

### Fixed
Nothing — no bugs were found in this module, matching Phase 6.

## [Unreleased] — Master Directive Phase 7: Telegram + Notification System

Like Phases 5/6, this module's backend (`NotificationController`,
`NotificationService`, `TelegramNotificationService`,
`ConversationController`) already existed from a prior session but had
never been executed live or covered by a test. Live-tested end to end,
found and fixed one real bug, wrote its first automated coverage, and
built its frontend.

### Added
- Frontend: a Notifications page (list, filter to unread, mark
  read/mark-all-read), a Conversations page (unified inbox across
  website_chat/email/whatsapp/telegram/sms/internal — filter by channel
  and status, create a conversation, open a thread to see every message
  with its honest per-message delivery status, reply or leave an
  internal note, assign, change status), and a Profile page (the only
  place a user's `telegram_chat_id` — where their Telegram-channel
  notifications actually go — can be set; no such UI existed anywhere
  before this phase).
- `tests/Feature/Phase7NotificationsTelegramTest.php` (15 tests) — the
  first automated coverage this module has ever had: notification
  lifecycle + user/tenant scoping, a real lead-assignment notification
  firing, profile `telegram_chat_id` persistence, conversation creation
  (including the customer-or-lead requirement and the `external_thread_id`
  fix below), reply delivery status across telegram/internal channels
  including the "no chat id" and "no bot token configured" honest-failure
  paths, internal notes never attempting delivery, inbound-message
  unread-count/reopen behavior, and tenant scoping.

### Fixed
- `POST /api/v1/conversations` silently dropped `external_thread_id` —
  present on the `conversations` table and load-bearing for
  `attemptDelivery()`, but missing from `store()`'s validation whitelist.
  Real-world effect: there was no way through the API to create a
  Telegram (or connector-based WhatsApp/SMS) conversation with an actual
  target chat id — every reply would permanently record
  `delivery_status: not_attempted`. Same bug class as Phase 4's
  `embassy_id` and Phase 5's status-enum finding: a real, schema-backed
  field silently dropped by an incomplete validation array. Fixed by
  adding it to the whitelist.

## [Unreleased] — Master Directive Phase 6: Custom Package Builder + Pricing Engine

Like Phase 5, this module's backend (`PricingRuleController`,
`PackageBuilderController`, `PricingEngine`, `InventoryResolver`) already
existed from a prior session but had never been executed live or covered
by a test. This phase live-tested it end to end, wrote its first
automated coverage, and built its frontend for the first time. Unlike
Phase 4/5, the audit and live-testing found **zero bugs** — every
endpoint behaved exactly as its code and schema describe.

### Added
- Frontend: Pricing Rules page (create/activate/deactivate/delete rules,
  a "Preview Calculation" tool showing the full applied-rules breakdown
  for an arbitrary base cost/context) and a Package Builder page (pick
  real hotel room types / flights / transports as line-item components,
  build against real inventory and see the resolved cost + pricing
  breakdown, optionally save as a reusable package or generate a
  quotation against a lead).
- `listTransports` API wrapper and a `Transport` frontend type — the
  backend has had a full transports CRUD route since Phase 4's route-gap
  fixes, but no page had ever called it; needed here to populate the
  Package Builder's transport picker.
- `tests/Feature/Phase6PricingPackageBuilderTest.php` (11 tests) — the
  first automated coverage this module has ever had: pricing rule CRUD +
  tenant scoping, preview calculation (matching rule application,
  non-matching/inactive rules correctly skipped, multi-rule compounding
  in priority order), and package builder (real inventory resolution and
  cost totals, cross-tenant component rejection, insufficient-capacity
  rejection, save-as-package persistence, create-quotation's `lead_id`
  requirement and the resulting quotation's line items).

### Fixed
Nothing — no bugs were found in this module. Flagging this explicitly
per the project's "report honestly" rule rather than silently having an
empty section.

## [Unreleased] — Master Directive Phase 5: Hajj & Umrah + Student Visa

The backend for this module already existed from a prior session
(`HajjController`, `UmrahController`, `HajjUmrahGroupController`,
`PilgrimController`, `StudentVisaController` and their models/migrations)
but had never been executed against a live database or exercised by a
frontend. This phase's work was auditing it, live-testing every endpoint,
fixing the one real bug that surfaced, writing its automated test suite,
and building its frontend for the first time.

### Added
- Frontend: Hajj & Umrah page (Hajj Packages / Umrah Packages / Groups
  tabs — create + edit Hajj packages, create-only Umrah packages since no
  update route exists for them, create groups against either package
  type with filters by type/status), a Group detail page (Pilgrims tab —
  register pilgrims, assign room/transport, record payments; Report tab
  — totals and by-status breakdown), and a Student Visa page (create
  applications, update application status, record offer letter, schedule
  embassy appointment, update visa status). No delete/destroy UI exists
  for any of these — the backend has no destroy routes for this module.
- `tests/Feature/Phase5HajjUmrahStudentVisaTest.php` (12 tests) — the
  first automated coverage this module has ever had: full CRUD/action
  lifecycles for Hajj/Umrah packages, groups + report, pilgrims + the
  group-at-capacity rejection, and the full student visa status workflow,
  plus tenant-scoping checks on every list endpoint.

### Fixed
- `HajjController::update()` accepted `status: sold_out` in its
  validation, but the `hajj_packages.status` database column is an enum
  of `active, inactive, discontinued` — that status was never a real
  option, and attempting to set it crashed with a SQL check-constraint
  violation instead of a normal validation error. Caught by this phase's
  own test, not by static checking. Fixed the validation to match the
  real schema.
- `utils/format.ts`'s `getErrorMessage()` only ever read a `message` key
  off a failed response, but `POST /pilgrims`'s at-capacity rejection
  returns `{"error": "Group is at full capacity"}` — that real backend
  error text was being silently swallowed and replaced with a generic
  fallback everywhere in the app, not just this phase's pages. Now reads
  `error` as well as `message`.

## [Unreleased] — Master Directive Phase 4: Travel Operations

### Added
- Booking calendar (`GET /bookings/calendar?from=&to=`).
- Embassy entity (new `embassies` table, `VisaApplication.embassy_id`
  FK) — the old `embassy` free-text column stays for backward
  compatibility.
- Hotel room blocks — group inventory holds distinct from the existing
  per-guest `hotels/book` flow, with a create/release/CRUD API.
- Visa/passport expiry reminder system: `ExpiryReminderService`, a
  daily scheduled command (`reminders:check-expiry`), and an on-demand
  endpoint. Idempotent — never double-creates a reminder for the same
  expiring record.
- Document attachment support for flight and hotel bookings (the
  whitelist only covered `booking`/`visa_application` before).
- **Package CRUD** (`GET/POST/PUT/DELETE /packages`) — didn't exist at
  all before this phase, despite `Booking.package_id` and
  `QuotationItem.package_id` depending on it entirely; found live-
  testing the booking creation form, whose package picker had nothing
  to populate it with.
- Frontend: Packages, Bookings (+ calendar + detail), Flights (+ seat
  booking), Hotels (+ room types + room blocks), Visas (+ embassies +
  expiry-check trigger).

### Fixed
- **8 previously-broken CRUD routes across the whole app**, found by a
  systematic scan (every `apiResource(...)` checked against its
  controller's actual methods): `customers`/`tasks` DELETE named
  `delete()` instead of `destroy()`; `bookings`/`hotels`/`flights`/
  `destinations`/`suppliers`/`visas` missing `update`/`destroy`/`show`
  entirely. Every one of these 500'd with "method does not exist" the
  moment anything called them — invisible to `php -l`, never caught
  until this phase's live end-to-end testing.
- `VisaController::store` silently dropped `embassy_id` (missing from
  its validation whitelist) even after the column and model relation
  were added — fixed alongside adding `update()`.
- Frontend: `/flights/book` actually takes `{travelers: [id, ...]}`
  (auto-assigns seats) and requires `currency` on flight creation and
  `H:i:s`-formatted times (the native `<input type="time">` only gives
  `HH:MM`) — my own spec to the page's builder was wrong on all three
  points; fixed the request payloads and the Book Seat form to match
  the real contract.

## [Unreleased] — Master Directive Phase 3: Sales + Quotation

### Added
- Lead → Customer conversion (`LeadConversionService`), wired into both
  code paths that can move a lead to 'won'
  (`LeadController::updateStatus`, `PipelineController::updateLeadStage`):
  reuses an existing customer (matched by email, then phone) before ever
  creating a new one, and backfills `customer_id` onto any of that
  lead's quotations that predate the win.
- Quotation → Booking conversion (`POST /quotations/{id}/convert-to-booking`):
  only from an `accepted` quotation, reuses the customer already linked
  via the quotation or its lead — never creates a duplicate.
- Quotation → Invoice generation (`POST /quotations/{id}/generate-invoice`):
  pre-populates every invoice item/total directly from the quotation
  instead of requiring the items to be re-entered by hand.
- Invoice PDF (`GET /invoices/{id}/pdf`, `InvoicePdfController`,
  `resources/views/pdf/invoice.blade.php`) — quotations already had one,
  invoices didn't.
- Sales Dashboard (`GET /sales/dashboard`): revenue this month,
  quotation conversion rate, invoice collection rate, outstanding
  amount, top packages by revenue.
- Customer quotation history (`GET /customers/{id}/quotations`).
- `POST /invoices/{id}/record-payment` as an alias for the existing
  `POST /invoices/{id}/payments` (both work).
- Frontend: Sales Dashboard, Quotations (list + detail, full approval
  workflow, PDF download, Create Proposal, Generate Invoice), Proposals
  (send/sign/reject), Invoices (create, record payment, PDF download,
  overdue highlighting) — all wired to the endpoints above.
- `tests/Feature/Phase3SalesQuotationTest.php` (12 tests).

### Fixed
- `QuotationController::store` didn't auto-populate `customer_id` when
  the lead it's created against had already converted (the common
  order of events) — every such quotation silently had no `customer_id`
  and never showed up in that customer's history. Now backfilled
  automatically, overridable by passing `customer_id` explicitly.
- `POST /quotations/{id}/generate-invoice` required `due_date` in the
  request body, but the frontend's one-click "Generate Invoice" button
  (as specified) sends no body at all — every real click would have
  422'd. Made `due_date` optional with a Net-14 default.
- **Two frontend bugs caught only by actually submitting forms through
  a live browser** (Phase 2's E2E pass only exercised navigation, never
  form submission): `CustomersPage`'s create/edit form sent a field
  named `type`, but the backend's real column and validation key is
  `customer_type` — every customer creation through the UI 422'd
  silently. The customer list also read `c.type` back for display,
  which the API never returns (it returns `customer_type`) — the
  column always showed "—" even for customers that did have a type.
  Fixed both the write and the read side, and the TypeScript `Customer`
  type to match the real field name.
- Quotations/invoices lists and the quotation detail page showed raw
  IDs ("Customer #1", "Lead #1") instead of names, despite the backend
  already eager-loading and returning the nested `customer`/`lead`
  objects — the frontend just wasn't reading them. Fixed to prefer the
  real name, falling back to the ID only if the relation is absent.

Older documents under `DOCUMENTATION/` use an earlier, unrelated "Phase 2"
numbering from a prior webhook/integration development cycle
(`PHASE_2_STATUS.md`, `PHASE_2_HANDOVER.md`) — those predate this
directive and are not the same Phase 2 referenced here. See
`DOCUMENTATION/PHASE_2_REPORT.md` for the disambiguation this file uses.

## [Unreleased] — Master Directive Phase 2: Complete CRM

### Added
- `Deal` entity (new `deals` table + `deal_stage_transitions` table):
  a genuine sales-opportunity model distinct from `Lead`/`DealStage`
  (which only tracks a lead's own status-transition history). Full CRUD,
  stage-change endpoint that records transition history, and a
  Kanban-style pipeline endpoint (`GET /api/v1/deals/pipeline`).
- Customer 360 profile endpoint (`GET /api/v1/customers/{id}/360`)
  aggregating a customer's leads, deals, bookings, quotations,
  communications, notes, tags, and timeline into one response.
- CRM Dashboard endpoint (`GET /api/v1/crm/dashboard`): total leads, new
  leads this month, conversion rate, pipeline value by stage, deals
  won/lost, tasks due today, upcoming follow-ups.
- Conversion funnel endpoint (`GET /api/v1/crm/conversion-funnel`):
  lead counts per funnel stage and overall conversion rate.
- Follow-up calendar endpoint (`GET /api/v1/crm/follow-ups/calendar`):
  merges reminders, lead follow-up dates, and task due dates into one
  date-ranged event feed.
- Sales activity history read endpoint
  (`GET /api/v1/pipeline/sales-activities`) — the `SalesActivity` model
  already existed with a write path via `PipelineController@recordActivity`,
  but had no way to list/read it back.
- `GET/PUT/DELETE /api/v1/leads/{id}` — Lead CRUD was previously
  read/create-only (`index`, `store`, `show`); added `update` and
  `destroy`.
- First React + TypeScript frontend for the project (`/frontend`), the
  first UI of any kind in the repository. See `frontend/README.md`.
- `tests/Feature/Phase2CrmTest.php` — feature tests covering every new
  endpoint above.

### Fixed
- `Lead::$fillable` was missing `customer_id` even though the column has
  existed on the `leads` table since Phase 0/1 (with a migration comment
  explicitly noting its purpose: "so a customer's originating leads can
  be found without guessing"). Mass-assignment was silently dropping it
  on every `Lead::create()`, so no lead could ever actually be linked
  back to the customer it originated from. Added it to `$fillable` and
  added the missing inverse `Lead::customer()` relation.
- CORS blocked every request from the new frontend outright (empty
  `allowed_origins` by default, with no guidance in `.env.example` on
  what to set). Documented it there instead of silently leaving new
  frontend developers to discover it via a cryptic browser error.
- Three frontend/backend response-shape mismatches, caught only by
  live-browser end-to-end testing (not by `npm run build`/`typecheck`):
  `/deals/pipeline`, `/customers/{id}/360`, and `/crm/dashboard` are all
  wrapped in `{"data": ...}` like every other endpoint, but the
  frontend — written defensively before these endpoints were confirmed
  live — read one nesting level too shallow on all three, and assumed
  `/deals/pipeline` (and, identically, the pre-existing `/pipeline/full`
  for leads) returned an array when it's actually an object keyed by
  stage/status. Result before the fix: the leads and deals pipeline
  pages crashed outright, the customer 360 page crashed reading a
  customer's name off `undefined`, and the dashboard silently showed
  placeholder dashes and $0 instead of real numbers. Fixed all three
  response types/call sites and rewrote the pipeline-normalizing
  helpers to handle the real shape.

### Notes
- `DealStage` (existing, `lead_id`-keyed) is left as-is: it is Lead's own
  stage-transition history, a different concept from the new `Deal`
  entity's `DealStageTransition` (`deal_id`-keyed). Both are real,
  intentional, and non-overlapping.
- Lead sources and lead statuses remain plain string columns rather than
  their own manageable tables — flagged as a known gap, not silently
  dropped; see `DOCUMENTATION/PHASE_2_REPORT.md` §13 (Known Limitations).

---

## Prior history

Phases 0–1 (foundation, auth/RBAC) and a large body of earlier backend
work (sales/quotation, travel operations, Hajj/Umrah, pricing engine,
notifications, AI provider management, analytics, security hardening,
etc.) were completed in earlier sessions before this changelog file
existed. See the per-phase status documents under `DOCUMENTATION/` for
that history; this file starts tracking changes from Master Directive
Phase 2 onward.
