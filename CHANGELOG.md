# Changelog

All notable changes to the Qudrix Travel CRM/ERP project, following the
master development directive's phase numbering (Phase 0 = Foundation,
Phase 1 = Backend Foundation + Auth + RBAC, Phase 2 = Complete CRM, ...).

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
