# Phase 0 — Verification Checklist

This environment has no `vendor/` (composer dependencies not installed) and no configured database, so
none of the commands below were executed here. Every item is `UNVERIFIED — REQUIRES LOCAL/STAGING
EXECUTION`. What *was* verified in this environment: `php -l` (lint) on every new/changed PHP file —
all passed with no syntax errors.

| # | Command | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| 1 | `composer install` | Dependencies installed, `vendor/` created | Not run (no network/DB in this session) | UNVERIFIED |
| 2 | `cp .env.example .env && php artisan key:generate` | `APP_KEY` set | Not run | UNVERIFIED |
| 3 | `php artisan migrate:fresh --seed` | All 16 migrations run, including new `2026_08_30_000016_create_phase0_foundation_tables` | Not run | UNVERIFIED |
| 4 | `php artisan test` | Existing 45+ tests pass, no regressions from Phase 0 changes | Not run | UNVERIFIED |
| 5 | `php artisan route:list --path=api/v1/vendors` | Shows `GET/POST /api/v1/vendors`, `GET/PUT /api/v1/vendors/{id}` | Not run | UNVERIFIED |
| 6 | `php artisan route:list --path=api/v1/support-tickets` | Shows ticket CRUD + `/status`, `/escalate`, `/reply` routes | Not run | UNVERIFIED |
| 7 | `ADMIN_URL_PATH=backoffice php artisan route:list --path=api/v1/backoffice` | Admin routes appear under the custom prefix instead of `/admin` | Not run | UNVERIFIED |
| 8 | `php -l` on all new files | No syntax errors | **Ran in this session** | **PASS** |

## Known pre-existing issue found during audit (not introduced by Phase 0)

`routes/api.php` references controllers as bare strings (e.g. `'CustomerController@method'`) without a
`Route::controller()` wrapper or namespace group. Laravel 8+ removed implicit controller namespacing, so
these route definitions will throw `ReflectionException: Class "CustomerController" does not exist` at
runtime unless resolved. This predates Phase 0 and affects most of the existing route groups, not just
the ones added here. **Flagged for Phase 1 (Backend + Auth + RBAC hardening)** — fixing it now would
expand Phase 0 beyond "foundation only" and touch every existing route group at once.
