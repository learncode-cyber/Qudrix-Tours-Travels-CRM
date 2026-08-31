# Qudrix Travel CRM — Frontend

A React 18 + TypeScript + Vite frontend for the Qudrix Tours & Travels CRM, talking to the
Laravel API in `../PROJECT`.

## Requirements

- Node.js 18+ and npm
- The Laravel backend running and reachable (defaults to `http://localhost:8123/api/v1`)

## Setup

```bash
npm install
cp .env.example .env   # adjust VITE_API_BASE_URL if your backend runs elsewhere
npm run dev
```

The dev server prints a local URL (typically `http://localhost:5173`). Log in with a user that
already exists in the backend's database.

## Environment variables

| Variable              | Default                          | Description                    |
| ---------------------- | --------------------------------- | ------------------------------- |
| `VITE_API_BASE_URL`    | `http://localhost:8123/api/v1`   | Base URL of the Laravel API (v1)|

Set this in a `.env` (or `.env.local`) file at the project root — never hardcode the API URL in
source.

## Scripts

- `npm run dev` — start the Vite dev server
- `npm run build` — type-check (via `tsc -b`) and produce a production build in `dist/`
- `npm run typecheck` — run TypeScript in `--noEmit` mode
- `npm run lint` — run ESLint over the project
- `npm run preview` — preview the production build locally

## What's implemented

- JWT auth (`/login`) with token stored in `localStorage`; every request carries
  `Authorization: Bearer <token>` and a 401 response redirects to `/login`.
- Dashboard with KPI cards and pipeline-value-by-stage, backed by `GET /crm/dashboard` with a
  client-side fallback (computed from `/pipeline/full` and `/tasks/stats`) if that endpoint isn't
  live yet.
- Customers list + create form, and a Customer 360 detail page (`GET /customers/{id}/360`) that
  falls back to the plain `GET /customers/{id}` record if the 360 endpoint isn't available.
- Leads list/kanban (driven by `GET /pipeline/full`) with a stage-move dropdown per lead, and a
  new-lead form.
- Deals list/kanban (driven by `GET /deals/pipeline`, falling back to grouping `GET /deals` by
  stage client-side) with the same stage-move pattern, or an honest "not available" message if
  the deals endpoints aren't live yet.
- Tasks list with complete/incomplete state, due dates, and a new-task form.

Every screen renders an explicit loading, error, empty, or "not available" state rather than
fabricating data — nothing here is hardcoded sample data.
