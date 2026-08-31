# Changelog

All notable changes to the Qudrix Travel CRM/ERP project, following the
master development directive's phase numbering (Phase 0 = Foundation,
Phase 1 = Backend Foundation + Auth + RBAC, Phase 2 = Complete CRM, ...).

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
