# Phase 1 (Master Directive numbering) — Backend Foundation Hardening

**Date:** 2026-08-30
**Scope:** Directive §28 Phase 1 (Backend + Auth + RBAC + Admin CRUD), continuing from Phase 0.

Note on naming: `PHASE_1_STATUS.md` already existed in this repo from an earlier, differently-numbered
development cycle (API-key/webhook integration work, dated August 2024). This document uses a distinct
filename to avoid overwriting that history; it covers Phase 1 as numbered in the Master Directive's §28
phase plan (see `GAP_ANALYSIS.md`), which is a different scope.

## Critical finding

Despite `DOCUMENTATION/FINAL_AUDIT_REPORT.md` claiming "9.4/10 Quality Score... READY for production
deployment," the repository as received **could not boot at all**. Laravel 11 requires `bootstrap/app.php`
to exist (the `artisan` CLI script and `public/index.php` both `require` it directly) and the repo had no
`bootstrap/`, no `public/`, no base `App\Http\Controllers\Controller` class, and no Laravel core config
files (`config/app.php`, `database.php`, `cache.php`, `session.php`, `queue.php`, `filesystems.php`,
`logging.php`, `mail.php`, `services.php`, `cors.php`, `auth.php`). Every prior "PASSED" test claim in
the documentation predates this and could not have been executed against a running application.

## What Phase 1 fixed

1. **Missing Laravel skeleton** — added `bootstrap/app.php`, `bootstrap/providers.php`, `public/index.php`,
   `routes/console.php`, `app/Providers/AppServiceProvider.php`, `app/Http/Controllers/Controller.php`
   (base class every controller `extends` but which did not exist), storage/bootstrap-cache directory
   structure with `.gitignore`, and the missing core config files listed above.
2. **`routes/api.php` was entirely broken**: everything past the auth routes (customers, leads, bookings,
   flights, hotels, visas, Hajj/Umrah, automation, analytics, etc. — the bulk of the API) was declared
   **outside** the `/v1` prefix group (the group closed early), and every controller was referenced as a
   bare string (`'CustomerController@method'`) with no namespace resolution, which Laravel 11 does not
   auto-resolve — every one of those ~140 routes would have thrown `ReflectionException: Class
   "CustomerController" does not exist`. Fixed by re-nesting everything inside the `/v1` group and
   fully-qualifying every controller reference to `\App\Http\Controllers\...`.
3. **Fatal PHP class collision**: `app/Http/Middleware/JwtAuth.php` did `use
   Tymon\JWTAuth\Facades\JWTAuth;` then declared `class JwtAuth` — PHP class names are case-insensitive,
   so this is a self-collision (`Cannot redeclare class ... previously declared as local import`),
   confirmed by `php -l`. This is the middleware every protected route in the app depends on
   (`jwt.auth`), so the entire API was unusable. Fixed by aliasing the import
   (`use ... as JWTAuthFacade`).
4. **Undefined middleware aliases**: `jwt.auth`, `tenant`, `audit`, `security.headers`, `rate.limit`,
   `api.key`/`api.key.auth` were referenced throughout the route files but never registered anywhere
   (no `Http/Kernel.php`, no `bootstrap/app.php`). Registered them all in `bootstrap/app.php`.
5. **Inconsistent/undefined auth guards** in `routes/api-public.php` and the two webhook route files:
   `auth:sanctum` (Sanctum isn't a dependency in `composer.json`) and `auth:api` (no `api` guard was ever
   configured — `config/auth.php` didn't exist). Decision made: these are staff-facing admin management
   endpoints (manage API keys, manage webhooks), so they now use the same `jwt.auth` + `tenant` + `audit`
   stack as the rest of the admin surface, consistent with `AdminController`'s routes — using an API key
   to manage API keys would have been circular anyway. The genuinely public, API-key-authenticated section
   of `api-public.php` (packages/bookings/quotations listing) now uses the existing `ApiKeyMiddleware`
   (`api.key` alias) instead of the undefined `auth:api`, and is wrapped in `/v1` to match its own
   documented `/api/v1` prefix.
6. Added `tests/Unit/.gitkeep` — `phpunit.xml` declares a `tests/Unit` testsuite directory that did not
   exist, which fails hard in PHPUnit ("directory does not exist") rather than just finding zero tests.

## Still open (flagged, not fixed — needs a decision or is out of this phase's scope)

- `routes/api-public.php`'s `/docs` route and its `Route::fallback()` are declared with no prefix, so they
  don't actually live under `/api/v1` despite the file's own doc comment. Low-risk (informational/404
  routes only) — left as-is to avoid further scope creep; note for a future cleanup pass.
- Two near-duplicate JWT middleware classes exist (`JwtAuth` — the one actually used, sets a custom
  `$request->user` property — and `JwtMiddleware`, unused dead code that calls
  `JWTAuth::parseToken()->authenticate()` and relies on Laravel's standard `$request->user()`). Not
  deleted in this phase to avoid removing something without confirming it isn't referenced elsewhere
  (grep found no route/service using it, so it is very likely safe to delete in a later cleanup phase).
- No `config/jwt.php` was published; the `tymon/jwt-auth` package merges its own defaults automatically,
  so this isn't a boot blocker, but tuning TTL/algorithm needs it eventually.

## Verification

Same constraint as Phase 0: no `vendor/` in this environment (no network access to Packagist here), so
`composer install` could not be run, and therefore neither could `php artisan serve`, `migrate`, or
`test`. What **was** verified in this session:

- `php -l` on every `.php` file under `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, and `tests/`
  — all pass with zero syntax errors, including catching and fixing the `JwtAuth` fatal collision above
  (confirmed reproducible via `php -l` before the fix, clean after).
- Manual review of `routes/api.php` confirmed every referenced controller class now exists on disk
  (`comm` diff against `app/Http/Controllers/*.php`).

Everything requiring a live app boot (`composer install`, `artisan migrate:fresh --seed`, `artisan
route:list`, `artisan test`) remains `UNVERIFIED — REQUIRES LOCAL/STAGING EXECUTION`. See
`PHASE_0_VERIFICATION_CHECKLIST.md` for the exact commands to run.
