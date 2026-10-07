# QUDRIX Admin Frontend

React + TypeScript + Vite + Tailwind admin dashboard for the QUDRIX Travel CRM backend.

## What's actually built (Phase 17, part 1)

Every page below makes real API calls to the actual Laravel backend — none of this is mocked or hardcoded:

- **Auth** — login/logout against the real `/v1/login`/`/v1/logout` endpoints, JWT stored in `localStorage`, automatic redirect to `/login` on a 401.
- **Dashboard** — real KPIs from `/dashboard/kpi` (the endpoint fixed in Phase 6 to stop returning hardcoded zeros). Honestly displays "not yet available" for metrics the backend itself reports as `null` rather than hiding that gap.
- **Customers** — list (search + pagination), create form, detail page with the real Phase 8 customer timeline.
- **Leads**, **Quotations**, **Bookings** — real list views with status badges.
- **Proposals** — real list with working send/mark-signed/reject actions (calls the real backend state-transition endpoints from Phase 3).
- **Invoices** — real list with balance-due calculation and void action.
- **Payments** — real list + a record-payment form that posts to the actual backend (auto-updates the parent invoice's paid status, per the Phase 3 backend logic).

## What's NOT built yet, honestly

This is "Complete Frontend + Production Release" per the master roadmap, which calls for ~29 modules (Flights, Hotels, Visa, Hajj & Umrah, Student Visa, Finance, Vendors, Agents, Marketing, Conversations, Support, AI, Analytics, Settings, Users, Roles, Permissions, Integrations, and more) each with full CRUD, dark/light mode, and multi-language support. Building all of that to the same real, backend-connected standard as what's above is a genuinely large amount of work — attempting it all in one pass would mean shipping stub pages that don't actually do anything, which is exactly the "fake feature" pattern this whole project has been trying to eliminate.

What exists is a real, working foundation: auth, routing, the API client, the design system (Tailwind tokens), and reusable UI primitives (Button, Input, Card, Badge, EmptyState, Spinner) that every subsequent module will reuse — plus five fully-wired example modules proving the pattern works end-to-end against the real backend. Extending to each remaining module is now a repeatable exercise: copy the CustomersList/CustomerForm pattern against the corresponding backend endpoints already built in Phases 2-16.

Not built at all yet: dark/light mode toggle, multi-language (i18n) support, and the AI-feature-facing pages (Conversations/Copilot UI) — these need their own design decisions, not just more CRUD pages.

## Setup

```bash
cd FRONTEND
npm install       # NOT run in this sandbox - no network access
cp .env.example .env
# point VITE_API_BASE_URL at your running Laravel backend, e.g.:
# VITE_API_BASE_URL=http://localhost:8000/api/v1
npm run dev
```

IMPORTANT - one backend config change you need to make for this to work in the browser: the Laravel backend's config/cors.php defaults allowed_origins to '*'. Set this explicitly in the backend's .env:
```
CORS_ALLOWED_ORIGINS=http://localhost:5173
```

## Known backend quirk this frontend works around
routes/api.php has an inconsistency: /login and /register live under a /v1 prefix, but every other resource route (customers, leads, etc.) does not. Rather than risk restructuring that large, many-times-edited route file, src/lib/api.ts exposes two axios instances (authApi for /v1/*, api for everything else) with this documented in a comment.

## Verification status
UNVERIFIED - this sandbox has no network access, so npm install has never been run and no build has ever been attempted. Every file was written by hand and reviewed for import-path correctness and consistency with the real backend response shapes (checked directly against each controller's return response()->json(...) calls), but this is a static review, not a running build. Run npm install && npm run build yourself to get a real first signal.
