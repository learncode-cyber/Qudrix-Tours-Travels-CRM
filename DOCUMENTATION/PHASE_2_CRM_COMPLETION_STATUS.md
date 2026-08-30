# Phase 2 (Master Directive numbering) — CRM Completion

**Date:** 2026-08-30
**Scope:** Directive §28 Phase 2 / §3.B (CRM), continuing from Phase 1.

Note on naming: `PHASE_2_STATUS.md` / `PHASE_2_HANDOVER.md` / `PHASE_2_DELIVERABLES.txt` already exist
from the earlier, differently-numbered webhook/integration development cycle. This file is named
distinctly to avoid clashing with that unrelated history.

## Two more fatal bugs found and fixed during this phase's audit

1. **`app/Models/Models.php`** was a leftover "bundle" file (its own header comment says "Each model
   should be extracted to its own file") that re-declared `Role`, `Branch`, `Customer`, `Lead`, `Package`,
   `Booking`, and `Payment` — every one of which **already has its own dedicated file** with more complete
   implementations. Duplicate class declarations across two files under the same PSR-4 namespace are
   unsafe: an optimized/classmap Composer autoload build (`composer install --optimize-autoloader`, used
   in most production deploys) will hit "Class X is defined in two files" and silently pick one
   arbitrarily. Deleted the dead bundle file; the dedicated files were already the more complete versions
   in every case (verified `Role` side by side as a sample).
2. **`->nullable()` chained after `belongsTo(...)`** in 16 places across `Models.php` (now removed),
   `Customer`, `Lead`, `Task`, `Communication`, `AuditLog`, `Booking`, `Proposal`, `Quotation`,
   `QuotationItem`, `Role`: `Illuminate\Database\Eloquent\Relations\BelongsTo` has no `nullable()` method
   — a nullable foreign key needs no special relation call at all, Eloquent just returns `null` when the
   column is null. Every one of these would throw `Error: Call to undefined method ...::nullable()` the
   moment the relation was touched (e.g. `$customer->branch`, `$lead->assignedTo`, `$task->assignee`).
   Removed the bogus call everywhere.

## What Phase 2 added (Directive §3.B)

Per the spec's CRM checklist, added everything that was still missing after Phase 0/1 (which already had
working Leads, Customers, Communications, Tasks, Deal-stage history via `DealStage`, sales activity log
via `SalesActivity`, and `CustomerSegment`):

- **Companies + Contacts** (`Company`, `Contact` models/migration/controllers) — a company can have many
  contacts, and a contact can optionally link to a `Customer` record too.
- **Notes** — polymorphic (`notable_type`/`notable_id`), attachable to Lead/Customer/Booking/Quotation/
  SupportTicket via an explicit server-side whitelist (never resolving the morph class from client input).
- **Documents** — polymorphic, same whitelist pattern, with real file storage through Laravel's
  filesystem (`Storage::disk('local')`) — upload and delete both touch actual files, not just DB rows.
- **Tags** — a `Tag` model plus a `Taggable` trait (`morphToMany`) applied to `Lead` and `Customer`, with
  attach/detach endpoints, again against a whitelist of taggable entity types.
- **Custom Fields** — `CustomFieldDefinition` (admin-defined field per entity type) + `CustomFieldValue`
  (per-entity value), so any entity type can gain ad hoc fields without a schema migration per field.
- **Reminders** — per-user, optionally linked to any entity, with a `/reminders/due` endpoint.
- **Customer Timeline** — `CustomerTimelineController@show` aggregates a customer's communications,
  notes, documents, tasks, and bookings into one chronological feed, built entirely from real rows (no
  synthetic timeline data).

All new tables are in `database/migrations/2026_08_30_100000_create_phase2_crm_tables.php`; all new
routes are tenant-scoped and sit behind the same `jwt.auth`+`tenant`+`audit` stack as the rest of the
protected API.

## Known issue found but not fixed this phase (flagged for a later cleanup)

`Customer::leads()` is `hasMany(Lead::class)`, which by Eloquent convention expects a `customer_id`
column on `leads` — that column does not exist (leads capture email/phone/company as free text, not a FK,
since a lead by definition usually isn't a customer yet). Calling `$customer->leads` will throw a SQL
error. This predates this phase. Fixing it is a design decision (e.g., add `converted_to_customer_id` on
`leads`, pointing the relationship the other way) rather than a one-line fix, so it's left for the Sales
Pipeline phase where lead-to-customer conversion is implemented properly. `CustomerTimelineController`
deliberately does not touch this relation, to avoid surfacing the bug through the new timeline endpoint.

## Verification

Same constraint as Phase 0/1 — no `vendor/`, no DB, no network access to Packagist in this session.
`php -l` passes on every file in `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `tests/` (zero
errors), and every controller referenced in `routes/api.php` was confirmed to exist on disk via `comm`
diff. `composer install` / `artisan migrate` / `artisan test` remain `UNVERIFIED — REQUIRES
LOCAL/STAGING EXECUTION`.
