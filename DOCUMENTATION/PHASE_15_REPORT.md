# Phase 15 Report — Security + Access Logging

Like Phase 14, this module's backend (`SecurityLogController`,
`AccessLogMiddleware`, `AuditMiddleware`, `AdminController`, the
login-lockout logic already in `AuthController`) was built in a prior
session but had never actually been executed against a live database,
called with a real HTTP request, or covered by an automated test. This
phase's work was: (1) audit it, (2) live-test every endpoint end-to-end,
(3) write its automated test suite, (4) build its frontend, (5) verify
everything together. **Three real bugs found and fixed** — the most in a
single phase since Phases 4/5/7/11 combined, and the first phase where a
bug was found purely by tracing an authorization path rather than by a
missing `$fillable`/validation field.

## 1. What was implemented

- **Audit**: route-to-controller cross-reference for the admin-prefixed
  group (`SecurityLogController`, `AdminController`), plus a full read of
  `AccessLogMiddleware`, `AuditMiddleware`, and the login-lockout code in
  `AuthController`. Three findings came directly from this reading, not
  from a failing request — the same "read the code against its own
  neighbors and migrations" method that found Phase 14's bugs, applied
  here to authorization instead of data columns:
  1. `AdminController`'s every method calls `$this->authorize('admin')`
     — but nothing in the codebase ever defines an `admin` Gate ability.
  2. `SecurityLogController` sits in the *same* route group, under the
     same "requires super-admin role" comment, but calls no
     authorization check at all — an inconsistency with its sibling.
  3. `AuthController::register()`'s role-attach logic looks up
     `$tenant->roles()->where('name', 'super-admin')`, which doesn't
     match how this app's actual system roles are seeded
     (`RoleSeeder`: global, `tenant_id` null, name `super_admin`).
  All three were confirmed live (curl, real seeded users) before being
  fixed — see §8/§9.
- **Live backend verification**: confirmed finding 1 and 2 live before
  any fix (`GET /admin/backups` returned `403` for the real seeded
  admin; `GET /admin/security/summary` returned `200` with real data for
  *any* authenticated user, not just an admin), applied all three fixes,
  then re-verified live: the seeded admin now gets `200` from
  `/admin/backups` and `/admin/security/summary`; a freshly created
  plain user gets `403` from the security trail; a fresh
  `POST /register` call now produces a user holding `super_admin` who
  can immediately reach an admin-gated endpoint. Also confirmed real
  `AccessLog` rows accumulate from ordinary traffic and that a
  `403`/forbidden request is flagged `is_suspicious` with the correct
  reason string.
- **Frontend** (new, first UI this module has ever had):
  - `SecurityPage.tsx` — a 24-hour summary (stat cards), and three tabs:
    Access Logs (with a suspicious-only filter), Audit Logs, Failed
    Logins. A non-admin sees an explanatory message ("Only an
    administrator can view the security trail...") rather than a raw
    403, matching the backend's actual gate.
- **Automated tests**: `tests/Feature/Phase15SecurityAccessLoggingTest.php`
  — 17 tests, the first this module has ever had.

## 2. Files / modules changed

Backend:
- `tests/Feature/Phase15SecurityAccessLoggingTest.php` (new)
- `app/Http/Middleware/JwtAuth.php` — now also calls `Auth::setUser($user)`
  (bug fix, see §8, finding 1)
- `app/Providers/AppServiceProvider.php` — defines the previously-missing
  `Gate::define('admin', ...)` (bug fix, see §8, finding 1)
- `app/Http/Controllers/SecurityLogController.php` — added
  `$this->authorize('admin')` to all four methods (bug fix, see §8,
  finding 2)
- `app/Http/Controllers/AuthController.php` — fixed the self-registration
  role lookup (bug fix, see §8, finding 3)

Frontend:
- `frontend/src/pages/SecurityPage.tsx` (new)
- `frontend/src/api/endpoints.ts` — added `getSecuritySummary`,
  `getAccessLogs`, `getAuditLogs`, `getFailedLogins`
- `frontend/src/types/index.ts` — added `AccessLogEntry`, `AuditLogEntry`,
  `FailedLoginAttemptEntry`, `SecuritySummary`
- `frontend/src/App.tsx` — added the `/security` route
- `frontend/src/components/AppLayout.tsx` — added the "Security" nav entry

Documentation:
- `DOCUMENTATION/PHASE_15_REPORT.md` (this file)
- `DOCUMENTATION/API_DOCUMENTATION_COMPLETE.md` — Phase 15 addendum
- `CHANGELOG.md`, `PROJECT_STATUS.md` — updated

## 3. Database changes

None. `access_logs`, `audit_logs`, `failed_login_attempts`, `roles`,
`role_user` all already existed from prior-session migrations.

## 4. API changes

None — every route already existed. Three behavior corrections (not new
routes) landed this phase, all described in §8/§9.

## 5. Frontend changes

One new page and its route/nav entry, described in §1. Reused every
existing shared component (`Badge`, `Loading`, `ErrorBanner`,
`EmptyState`, `NotAvailable`, `StatCard`) and utility (`formatDate`,
`getErrorMessage`, `titleCase`).

## 6 & 7. Tests performed and results

**Automated (PASS):**
```
Tests:    272 passed (884 assertions)
Duration: 12.27s
```
(255 pre-existing + 17 new `Phase15SecurityAccessLoggingTest` tests, zero
failures, zero regressions.) The 17 new tests cover: the admin Gate
working for a real admin and denying a non-admin (`AdminController` and
all four `SecurityLogController` endpoints individually); the security
endpoints returning real, tenant-scoped data (access logs including a
`suspicious_only` filter, audit logs filtered by `entity_type`, failed
logins deliberately not tenant-scoped); self-registration granting
`super_admin` and that role actually opening an admin-gated endpoint
(this test would have failed on the pre-fix code, since the role
attachment never ran); login lockout after repeated failures (including
that a *correct* password is still rejected once locked out) and that a
successful login clears the failure streak; that the login error message
is identical for an unknown email vs. a wrong password; and that real
HTTP traffic through this test suite actually produces `AuditLog` rows
for writes and `AccessLog` rows for all requests, with a `403` correctly
flagged suspicious with the exact reason string.

**Manual/live (PASS):** Direct curl calls against a live `php artisan
serve` instance, using this sandbox's real seeded admin and a freshly
created plain user. Before any fix: `GET /admin/backups` as the real
admin returned `403` (bug 1, confirmed); `GET /admin/security/summary`
as a plain non-admin user returned `200` with real tenant data (bug 2,
confirmed). After the fixes: both now behave correctly — `200` for the
admin, `403` for the plain user. A fresh `POST /register` call was made
end to end: the created user's roles were inspected directly
(`$user->roles()->pluck('name')` → `["super_admin"]`, where it was
previously always empty), then that user's own token was used to reach
`/admin/backups` and got `200`.

Headless-Chromium Playwright E2E against the built frontend served via
`vite preview`, logged in as the real seeded admin: opened the Security
page, confirmed the summary stat cards rendered, and switched between
the Audit Logs and Failed Logins tabs — zero `pageerror` events. A
separate full-app sweep (all pre-existing pages across Phases 2–14) also
completed with zero page errors, confirming no regression.

**Build/typecheck (PASS):** `npx tsc --noEmit` silent; `npm run build`
succeeded (136 modules, no errors).

## 8 & 9. Bugs discovered and fixed

**Three bugs found — the most in a single phase since Phases 4/5/7/11
combined, and the first found purely from an authorization-path read
rather than a missing fillable/validation field.**

1. **The `admin` Gate ability was never defined, and JWT authentication
   never registered with Laravel's `Auth` facade — together, every
   `$this->authorize('admin')` call denied every request unconditionally,
   including from real admins.** `AdminController`'s five endpoints
   (database optimize/analyze, backup create/list/cleanup) have called
   `$this->authorize('admin')` since they were written, but no
   `Gate::define('admin', ...)` exists anywhere in the codebase — and
   even if it did, `Gate`/`AuthorizesRequests` resolve the current user
   via `Auth::user()` on the default (`web`/session) guard, while this
   app's real authentication (`JwtAuth` middleware) only ever sets
   `$request->user` as a plain dynamic property, never calling
   `Auth::login()` or `Auth::setUser()`. The combined effect: these
   endpoints have been completely unusable by anyone, ever, since they
   were built — and because the failure mode is a clean `403 Forbidden`,
   it looks exactly like "working as designed" from outside, which is
   why it survived undetected through however many prior sessions built
   this code. **Fixed** by (a) having `JwtAuth` also call
   `Auth::setUser($user)` after authenticating (in-memory only — no
   `Auth::login()`, so nothing is persisted to a session, keeping the
   API properly stateless), and (b) defining
   `Gate::define('admin', fn ($user) => $user->hasAnyRole(['super_admin', 'admin']))`
   in `AppServiceProvider::boot()`, using the real role system that
   already exists (`User::hasAnyRole()`, seeded roles). Verified live
   both before (403 for the real admin) and after (200) the fix, and by
   a new test asserting both directions (admin allowed, non-admin still
   denied).
2. **`SecurityLogController`'s four methods (`accessLogs`, `auditLogs`,
   `failedLogins`, `summary`) had no authorization check of any kind**,
   despite being mounted in the exact same
   `Route::prefix(config('admin.path'))->middleware(['app.jwt', 'security.headers'])`
   group as `AdminController`, under a comment that literally reads
   "Admin endpoints (require super-admin role)" — and despite
   `AdminController`'s own methods enforcing this with
   `$this->authorize('admin')` on every single one. Before this fix, any
   authenticated user in any tenant with any role — not just an admin —
   could read that tenant's complete access log, audit log, and
   failed-login history: exactly the trail meant to catch unauthorized
   access, itself unauthorized-accessible. Confirmed live: a plain user
   with no roles got a real `200` with real data from
   `/admin/security/summary`. **Fixed** by adding the identical
   `$this->authorize('admin')` call used by its sibling controller to
   all four methods. Verified live (403 for the plain user, 200 for the
   real admin) and by four new tests, one per endpoint.
3. **Self-registration silently granted zero roles to every new
   account.** `AuthController::register()` — the actual handler behind
   `POST /register`, the only way to create a brand-new tenant via the
   API — looks up
   `$tenant->roles()->where('name', 'super-admin')->first()` (a
   tenant-scoped query for a hyphenated name) to find the role to attach
   to the new user. But this app's real system roles are seeded
   globally by `RoleSeeder` (`tenant_id` null, name `super_admin` with
   an underscore) — a completely different query shape and a completely
   different name. The lookup therefore always returned `null`, the
   `if ($superAdminRole)` branch never ran, and every self-registered
   tenant's founding user ended up a fully authenticated account with
   zero roles — unable to pass `hasRole()`/`hasAnyRole()` checks
   (including the now-real `admin` Gate from finding 1) forever, with no
   error or warning anywhere. (There is also a dead, unused
   `AuthService::register()` in the codebase with internally-consistent
   but *different* logic — it seeds its own hyphenated tenant-scoped
   roles correctly — but nothing calls it; the actual route uses
   `AuthController::register()` directly.) **Fixed** by changing the
   lookup to `Role::whereNull('tenant_id')->where('name', 'super_admin')->first()`,
   mirroring exactly how `DatabaseSeeder` finds the same role for the
   seeded admin, and attaching it with the correct pivot `tenant_id`.
   Verified live end to end: a fresh `POST /register` call followed by
   inspecting the new user's roles directly showed `["super_admin"]`
   (previously always `[]`), and that user's own token could
   immediately reach an admin-gated endpoint.

All three matched the project's standing rule to report bugs honestly
when found, and — notably — none of them were the recurring
"missing-fillable-field" class from Phases 4/5/7/11/14; this phase's
audit method (tracing an authorization path end-to-end against its own
sibling code, rather than checking one model's fillable array) surfaced
a different class of defect that unit tests calling one endpoint in
isolation would not have caught, since each individual piece "looked"
correct — the bug was in how they fit together.

## 10. Regression results

Full suite: 272/272 passing (was 255/255 before this phase — net +17,
zero failures introduced). Full-app Playwright sweep across all
pre-existing pages plus this phase's new page: zero `pageerror` events.
No existing behavior changed except the three fixes in §8/§9, all of
which only add previously-missing/broken authorization — no existing
passing test or verified flow (that didn't depend on the broken
behavior) was altered. `AdminController`'s five endpoints and
`SecurityLogController`'s four endpoints go from "unconditionally
broken/unconditionally open" to "correctly gated" — a strictly-better
state, not a behavior change any legitimate caller depended on.

## 11. Security considerations

- **This entire phase is security remediation.** Findings 1 and 2 in
  §8/§9 are real authorization defects with real impact: finding 1 made
  a whole controller unusable (availability/functionality bug with a
  security flavor — it *looked* like correct denial); finding 2 was a
  genuine authorization bypass (any authenticated user could read
  another role's-worth of sensitive audit data). Finding 3 meant the
  RBAC layer had no way to actually protect a self-registered tenant's
  own data, since its own admin held no role to check against.
- `AccessLogMiddleware` deliberately never stores request bodies (only
  method/URL/status/IP/user-agent/duration) specifically because bodies
  can carry passwords and provider credentials; the query string is
  further redacted for password/token/secret/credential-shaped keys.
- The login lockout key is `email + source IP`, not `email` alone,
  specifically so an attacker cannot lock a real user out globally by
  hammering their address from elsewhere — confirmed by reading the
  code; the lockout mechanism itself (not this specific IP-scoping
  nuance) was also exercised live by this phase's tests.
- The login error message is identical for "email doesn't exist" and
  "wrong password" — confirmed by a new test comparing both response
  bodies — so the endpoint cannot be used to enumerate valid accounts,
  even though the real reason is still recorded server-side for the
  (now properly gated) admin trail.
- No new secrets, API keys, or credentials introduced.

## 12. Known limitations

- **Route-level, per-permission RBAC (`RBACMiddleware` with the `rbac`
  alias) is still not applied to any route.** This phase fixed the
  narrower `admin`-vs-not Gate used specifically by
  `AdminController`/`SecurityLogController`; the broader "does this
  user's role permit this specific action" enforcement described in
  `PROJECT_STATUS.md`'s cross-cutting Known Limitations is unchanged and
  remains its own, larger, future pass — fixing it app-wide was
  explicitly flagged there as bigger than any single phase's scope.
- The dead `AuthService::register()`/`AuthService::login()` classes
  (unused by any route) were left in place rather than deleted — they
  are internally consistent on their own terms (a different, also-valid
  tenant-scoped role-naming scheme), just disconnected from the actual
  registration path. Removing genuinely dead code is a reasonable
  future cleanup but wasn't required to fix this phase's bug, and
  deleting it isn't part of what this audit was asked to verify.
- Same cross-cutting limitations as every prior phase (MySQL-only in
  production/SQLite-only verified here) — see `PROJECT_STATUS.md`.

## 13. UNVERIFIED items

- Production MySQL 8.0+ behavior — verified here against SQLite only.
- The broader per-permission RBAC rollout described in §12 remains
  entirely unimplemented at the route level, not merely unverified.

## 14. Deployment instructions

Identical to every prior phase — no new migrations, no new environment
variables:
```
composer install --no-dev --optimize-autoloader
# configure .env: DB_CONNECTION, DB_DATABASE (MySQL 8.0+ in production), etc.
php artisan key:generate --force
php artisan jwt:secret --force
php artisan migrate --force
php artisan db:seed --force        # capture the printed admin password
php artisan serve                  # or your real web server

# frontend
npm install
# set VITE_API_BASE_URL in frontend/.env to the backend's real URL
npm run build
# serve dist/ via your static host / reverse proxy
```

## 15. Verification commands + expected results

```bash
php artisan test --filter=Phase15SecurityAccessLoggingTest
# expect: Tests: 17 passed (45 assertions)

php artisan test
# expect: Tests: 272 passed (884 assertions)

# live curl (after login as a real admin holding super_admin or admin):
curl /api/v1/admin/backups
# expect: 200 (was 403 before this phase's fix)

# live curl (after login as any authenticated non-admin user):
curl /api/v1/admin/security/summary
# expect: 403 (was 200 with real data before this phase's fix)

# self-registration:
curl -X POST /api/v1/register -d '{"tenant_name":"...","name":"...","email":"...","password":"..."}'
# then inspect the created user's roles() — expect ["super_admin"] (was [] before this phase's fix)

cd frontend && npx tsc --noEmit && npm run build
# expect: build succeeds, typecheck silent
```
