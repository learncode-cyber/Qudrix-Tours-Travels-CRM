# Phase 3 (Master Directive numbering) — Sales & Quotation Completion

**Date:** 2026-08-30
**Scope:** Directive §28 Phase 3 / §3.C (Sales & Quotation), continuing from Phase 2.

Note on naming: `PHASE_3_STATUS.md` / `PHASE_3_HANDOVER.md` already exist from the earlier,
differently-numbered webhook-integration development cycle. This file is named distinctly to avoid
clashing with that unrelated history.

## What existed already

`Quotation`/`QuotationItem`/`Proposal` with a working create/list/show/send flow, tax/discount fields,
currency, `valid_until` expiry, and quotation stats. Solid starting point.

## What Phase 3 added (Directive §3.C checklist)

- **Invoices** — `Invoice`/`InvoiceItem` models, migration, `InvoiceController` (create with real line-item
  totals, record partial/full payments with automatic status recalculation — draft → sent →
  partially_paid/paid/overdue — `send`, and stats). This is also the foundation the Finance phase will
  build on.
- **Quote Templates** — `QuotationTemplate` model + CRUD controller (default items/payment terms/validity
  as a reusable starting point for new quotations).
- **Markup** — `quotation_items` gained `cost_price` + `markup_percentage`; when both are supplied,
  `QuotationItem::priceFromMarkup()` deterministically derives `unit_price` server-side (cost × (1 +
  markup%)) rather than trusting a client-supplied sell price — matches the directive's "final price
  calculation must remain deterministic and auditable" rule from the pricing-engine section.
- **Approval workflow** — `requires_approval`/`approved_by`/`approved_at` columns +
  `submitForApproval`/`approve` endpoints. A quotation can be routed through `draft → pending_approval →
  approved → sent`; `sendQuotation` now refuses to send anything still pending approval.
- **Version history** — `version` + `supersedes_quotation_id` columns and a `newVersion` endpoint that
  clones a quotation (and its items) into a new draft linked back to the original, so a sent quotation can
  be revised without losing the record of what the customer actually saw.
- **Shareable quotations** — every quotation gets a random 160-bit `share_token` on creation.
  `QuotationShareController` exposes `GET/POST /api/v1/public/quotations/{token}` (view, accept, reject)
  with **no JWT/tenant auth** — access is gated purely by the unguessable token, since the recipient is an
  external customer with no CRM account. Deliberately excludes draft/pending-approval quotations from the
  public view.
- **PDF quotation generation** — added `barryvdh/laravel-dompdf` to `composer.json`, a Blade template
  (`resources/views/pdf/quotation.blade.php`), and `QuotationPdfController@download`
  (`GET /api/v1/quotations/{id}/pdf`). This is the first Blade view the app uses, which also meant adding
  the still-missing `config/view.php` (another core Laravel config file absent from the original repo).

## More pre-existing bugs found and fixed while touching this area

- `Quotation` model's `fillable`/`casts` included a `terms` field with no matching database column
  (`quotations` only has `payment_terms`) — would have thrown a SQL error if ever populated. Removed;
  `payment_terms` (now cast as JSON, matching its actual column type — it wasn't cast before) is the real
  field.
- `QuotationController@store` never persisted `package_id` on quotation items even though the column and
  model relation exist. Fixed.

## Verification

Same constraint as prior phases — no `vendor/`, no DB, no Packagist access in this session, so
`composer require barryvdh/laravel-dompdf` itself could not be run; the dependency is declared in
`composer.json` and the code written against its documented API, but is **UNVERIFIED** until `composer
install` runs somewhere with network access. Everything else: `php -l` passes with zero errors across
`app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `tests/`, `resources/`; every controller
referenced in `routes/api.php` confirmed to exist on disk.
