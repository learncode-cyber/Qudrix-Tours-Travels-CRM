# QUDRIX AI Travel CRM — Gap Analysis (Phase 0)

**Date:** 2026-08-30
**Scope:** Audit of current repository against the Qudrix Master Development Directive (37 sections, 17 phases).

---

## 1. What Exists

The repository contains a Laravel 11 API-only backend (`PROJECT/`), previously developed through an
internal "Phase 1-4" cycle focused mostly on webhooks/integrations. It includes:

- 69 Eloquent models, 50 controllers, 31 services, 8 middleware classes.
- Modules with real logic: Leads, Customers, Bookings, Quotations, Proposals, Flights, Hotels, Visa
  applications, Hajj/Umrah packages, Suppliers, Tasks, Communications, Complaints, Automation engine,
  Webhooks (filtering/batching/conditional delivery/analytics), Reports/Analytics, Offline/PWA sync,
  Audit logging, RBAC + JWT middleware, multi-tenant scoping.
- 15 migrations / 68 tables, 45+ tests, extensive documentation under `DOCUMENTATION/`.

**Absent findings:** `vendor/` is not installed (no `composer install` run in this environment), so none
of the existing test suite could be executed here. All Phase 0 work below was written by hand, matching
existing code conventions, and is marked `UNVERIFIED — REQUIRES LOCAL/STAGING EXECUTION` until
`composer install && php artisan migrate:fresh --seed && php artisan test` is run in an environment with
network access to Packagist and a configured database.

## 2. What Is Missing (confirmed via full-repo grep, zero hits before this phase)

| Area | Spec section | Status before Phase 0 |
|---|---|---|
| Frontend (React+TS+Vite+Tailwind CRM UI) | §2, §3 (all modules) | **Missing entirely** — API-only repo, no client code |
| AI Provider Manager | §4 | Missing — no provider/model/credential/usage abstraction |
| AI Sales Agent / Copilot / Package Builder | §5, §6, §13 | Missing |
| Dynamic Pricing Engine | §7 | Missing (pricing logic embedded ad hoc in `QuotationService`) |
| Lead Scoring logic | §10 | `LeadScore` model exists but no scoring service/signals |
| HRM (employees, payroll, attendance, leave) | §M | Missing |
| B2B Agent Management (KYC, commission, payouts) | §L | Missing |
| Marketing (campaigns, coupons, affiliate, automation sequences) | §P | Missing (only a generic `CustomerSegment` model) |
| Unified Conversations layer (WhatsApp/Telegram/SMS/email inbox) | §N | Missing (only `Communication` log, not a channel-routing layer) |
| Support Tickets (SLA, escalation, categories) | §O | Missing (`Complaint` model is not a full ticketing system) |
| Student Visa dedicated workflow | §I | Missing (visa handling is generic, not student-specific) |
| Multi-language / i18n | §21 | Missing |
| Multi-currency conversion config | §22 | Partial — `Tenant.currency` exists, no conversion engine |
| Configurable admin URL | §20 | Missing — no admin path abstraction |
| Vendor vs Supplier separation | §K | Missing — only `Supplier` exists |
| Integration Manager / adapter architecture | §18 | Partial — webhook infra exists, no adapter registry with "CONTRACT REQUIRED" markers |

## 3. What Should Be Reused

- The existing Laravel domain models for Bookings/Flights/Hotels/Visa/Hajj/Umrah/Quotations are solid
  and should be extended rather than rebuilt.
- RBAC/JWT/Tenant middleware, audit logging (`AuditLog`, `AuditMiddleware`), and the automation engine
  are reusable foundations for the AI/automation phases.
- The webhook infrastructure (`app/Services/Webhook/*`) is a good pattern to follow for the future
  Integration Manager adapters.

## 4. Phase Plan (per Directive §28)

Phase 0 (this change) lays foundation-only pieces that many later phases depend on, without inventing
any external API integration:

1. Configurable admin URL (env-driven route prefix + config).
2. Vendor model, separated from Supplier.
3. Support Ticket module (tickets + replies), replacing ad hoc complaint-only handling.
4. HRM skeleton (`Employee` model) — full payroll/attendance/leave deferred to Phase HRM.
5. Multi-language scaffold (`config/languages.php`, `Translation` model for translatable business content).
6. AI Provider Manager skeleton (`config/ai.php`, `AiProvider`, `AiUsageLog` models) — provider-independent,
   credentials stored server-side only, no hardcoded vendor SDK calls yet (Phase 9 implements actual calls).

Subsequent phases (1-17) follow the directive's numbering: Backend/Auth hardening, CRM completion, Sales
& Quotation completion, Bookings/Flights/Hotels/Visa completion, Hajj-Umrah/Student-Visa, Package
Builder/Pricing Engine, Notifications/Automation, external integrations (contract-gated), AI Provider
wiring, AI Sales Agent/Package Builder, Sales Strategies/Copilot, Behavioral Analytics, Upsell/A-B
testing, AI Complaint Handling, Security hardening, Analytics, Final QA.

**No phase in this plan invents a third-party API endpoint.** Any connector without a supplied contract
(GDS, hotel API, payment gateway, WhatsApp Business API, etc.) is scaffolded as an adapter interface only
and marked `CONTRACT REQUIRED` in code and docs.
