# QUDRIX CRM — MASTER PROJECT PLAN

> এই ফাইল **roadmap** — কী বানানো হবে এবং কীভাবে। "এখন পর্যন্ত কী হয়েছে" জানতে `STATUS.md` দেখুন। এই দুটো ফাইল কখনো একে অপরের ডুপ্লিকেট না — এটা ইচ্ছাকৃত বিভাজন।

## 1. Project Vision

QUDRIX CRM একটি complete:
Travel CRM + Travel ERP + Hajj Management + Umrah Management + Student/Al-Azhar Management + Visa Management + Sales CRM + Finance + Marketing + AI Sales Platform + Automation + Complaint/Support + Multi-Tenant SaaS + Internationalization

— production-ready platform হিসেবে গড়ে তোলা, যেখানে public website ও private CRM আলাদা boundary-তে থাকবে।

## 2. Product Scope — Planned Services

Hajj · Umrah · Egypt tourism · Umrah+Egypt package · tourist/visit visa · work permit/employment visa assistance · student visa/education · Al-Azhar admission support · international air ticket · hotel booking · airport transfer · international tour packages · custom tour packages · family & group tours · corporate travel · travel documentation · travel insurance · immigration/relocation support · visa consultation

## 3. Business Modules

CRM (leads/customers/360/tags/pipeline) · Sales (enquiry→quotation→booking→invoice→payment) · Operations (flights/hotels/visa/transport/tours/supplier) · Hajj/Umrah (pilgrim/group/departure/installment) · Student/Al-Azhar · Finance · Agent/Supplier/Vendor · Communication (WhatsApp/Telegram/Email/SMS) · Complaint/Support · AI Platform · Dynamic Pricing · Multi-tenant SaaS · Internationalization · Website + Marketing attribution

## 4. Original Requirements — Reconstruction Status

`MASTER_PROJECT_AUDIT.md` (2026-09-18) already reconstructed the phase-by-phase roadmap by reading every `PHASE_N_REPORT.md` title directly — this is **RECONSTRUCTED, evidence-based**, not invented. No separate original planning document (e.g. a `PROJECT_STATUS.md` referenced by an old audit file) exists in the current project files, so anything beyond the phase report titles and this session's stated business scope is **NOT FOUND**.

## 5. Phase-by-Phase Plan

| # | Phase Name | Objectives | Status source |
|---|---|---|---|
| 1 | Backend Foundation Audit & Hardening | Multi-tenancy, auth, RBAC, admin CRUD base | PHASE_1_REPORT.md |
| 2 | CRM Core Completion | Leads/customers/timeline/tags core | PHASE_2_REPORT.md |
| 3 | Sales + Quotation System | Enquiry→quotation→booking flow | PHASE_3_REPORT.md |
| 4 | Travel Operations | Flights, hotels, visa, booking mgmt | PHASE_4_REPORT.md |
| 5 | Hajj/Umrah + Student Visa | Hajj/Umrah packages, ritual checkpoints, student visa | PHASE_5_REPORT.md |
| 6 | AI Custom Package Builder + Pricing Engine | Dynamic pricing rules | PHASE_6_REPORT.md |
| 7 | Communication + Notifications | WhatsApp/Telegram/email/SMS channels | PHASE_7_REPORT.md |
| 8 | CRM ↔ ERP Integration | — | PHASE_8_REPORT.md |
| 9 | AI Provider Management | Provider/model/usage abstraction | PHASE_9_REPORT.md |
| 10 | AI Sales Agent | — | PHASE_10_REPORT.md |
| 11 | Sales Strategies + AI Copilot | SPIN/Challenger/Sandler frameworks | PHASE_11_REPORT.md |
| 12 | Analytics + Behavioral Intelligence | — | PHASE_12_REPORT.md |
| 13 | Upsell/Cross-sell + A/B Testing | Experiments/variants | PHASE_13_REPORT.md |
| 14 | Complaint Handling + Automation | — | PHASE_14_REPORT.md |
| 15 | Security + Audit + Hardening | RBAC/policy fixes, API key security | PHASE_15_REPORT.md |
| 16 | SEO + Website Analytics | — | PHASE_16_REPORT.md |
| 17 | Complete Frontend + Production Release | React/TS/Vite frontend | PHASE_17_REPORT.md |
| 18 | Multi-tenant SaaS Readiness | Tenant branding, subscription plans, API keys | PHASE_18_REPORT.md; routing bug found+fixed 2026-09-18 |
| 18.P0 | P0 Remediation Batch 1 | Agent/Vendor separation, Supplier CRUD gap | P0_REMEDIATION_BATCH_1.md, done 2026-09-18 |
| 18.P1 | Hajj/Umrah depth + Al-Azhar + i18n | Mahram, room assignment, installment, Al-Azhar fields, বাংলা/আরবি/RTL | **IMPLEMENTED 2026-09-18**, see P1 report |
| 18.P2 | Marketing attribution + Commission ledger | Meta Pixel, Conversions API, GA4, commission reconciliation | **IMPLEMENTED 2026-09-19**, see P2 report |
| 19 | Billing / Subscription | Real payment gateway integration | **BLOCKED — no gateway credentials** |
| — | Final QA + Production Deployment | Never existed as its own phase — folded into 17, incompletely | **RECONSTRUCTED gap, NOT STARTED as a dedicated pass** |

## 6. Dependencies

- Phase 19 (Billing) — blocked on real payment-gateway credentials (Stripe/SSLCommerz/bKash etc., not provided)
- AI Sales Agent / AI Copilot real behavior — blocked on real AI provider API credentials
- Any runtime/migration/test verification — blocked on a PHP+MySQL(+Node for frontend) environment; this sandbox has none

## 7. Security Requirements
Tenant isolation on every model/controller (established pattern: `where('tenant_id', ...)` in every query) · RBAC via `Role`/`RBACMiddleware` · audit logging via `AuditMiddleware` · no dead/unscoped controllers (violated once by an orphaned `Api\AuthController`, removed 2026-09-18) · every registered route must map to an existing, implemented controller method (violated once by `Supplier::update/destroy`, fixed 2026-09-18) · every route file must actually be registered in `bootstrap/app.php` (violated by 3 files, fixed 2026-09-18)

## 8. SaaS Requirements
Tenant, tenant isolation, branding, subscription plans, roles/permissions, API keys, tenant settings, usage limits, feature entitlements — code-level structure exists from Phase 18; billing itself is Phase 19 (blocked).

## 9. AI Requirements
Provider abstraction (OpenAI/Gemini/Claude/LLaMA/Cohere/HuggingFace) via `AIProvider`/`AIFeatureConfig`/`AIUsageLog`; sales methodology frameworks (`SalesScript`/`SalesStrategyConfig`/`ObjectionResponse`); real behavior UNVERIFIED without live API credentials.

## 10. Internationalization Requirements
বাংলা, English, আরবি (RTL), currency/country/phone/timezone/date-format handling — **NOT STARTED**, currently only a static default locale in `config/app.php`.

## 11. Deployment Requirements
Target: Hostinger Business Shared Hosting. PHP, Laravel, Node/React build, MySQL, CORS, storage, queue, cron, cache, SSL — code-level config exists; **all runtime/production verification is UNVERIFIED — environment limitation**, no exceptions.

## 12. Final Production Goal
See Section 1. Full completion requires every phase above at **VERIFIED** status (see `STATUS.md`), not merely IMPLEMENTED.
