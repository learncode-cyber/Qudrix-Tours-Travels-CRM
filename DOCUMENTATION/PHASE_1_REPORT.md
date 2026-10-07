# QUDRIX Travel CRM — PHASE 1 REPORT
## Backend Foundation Audit & Hardening

**Date:** September 2, 2026
**Scope:** Static audit and repair of the existing Laravel backend. No new features added.
**Environment note:** This work was done in a sandbox with **no PHP interpreter, no MySQL, and no network/git access**. Every fix below is based on careful static reading of the code and Laravel 11 framework semantics, not on running the app. Nothing here should be taken as "tests passed" — see the Verification section for exact commands to run yourself.

---

## 1. Why this phase happened

The handover documentation (`DOCUMENTATION/FINAL_AUDIT_REPORT.md`) claimed:
- "VERIFIED FOR HANDOVER", "READY for production deployment"
- Overall Quality Score: 9.4/10
- 89/89 backend tests passing

None of this was achievable as delivered. The project could not have booted, let alone passed a test suite, in the state it was uploaded in. This phase found and fixed the reasons why.

---

## 2. Critical bugs found and fixed

### 2.1 The entire Laravel framework skeleton was missing
No `bootstrap/` directory, no `public/index.php`, no `storage/` tree, and most of `config/` (only 4 custom production-tuning files existed under `config/production/`). Laravel cannot boot without these. **Created:** `bootstrap/app.php`, `bootstrap/providers.php`, `public/index.php`, the full `storage/` directory tree, and `config/app.php`, `database.php`, `auth.php`, `jwt.php`, `sanctum.php`, `cache.php`, `session.php`, `queue.php`, `filesystems.php`, `logging.php`, `cors.php`, `routes/web.php`, `routes/console.php`.

### 2.2 117 broken API routes (the whole CRM surface)
`routes/api.php` used old-style bare string controller actions (`'CustomerController@addFamily'`). Laravel 8+ removed automatic namespace-prefixing for these, so every one of these would throw `Target class [CustomerController] does not exist`. This affected customers, leads, communications, tasks, quotations, proposals, pipeline, bookings, flights, hotels, Hajj/Umrah, visas, suppliers, and more — essentially the entire product.
**Fixed:** scripted, verified find/replace to fully-qualify all 117 references to `App\Http\Controllers\X`. Confirmed every referenced controller class exists on disk.

### 2.3 `app/Models/Models.php` duplicate-declared 7 classes
This file (its own header comment: *"Each model should be extracted to its own file"*) contained full class bodies for `Role`, `Branch`, `Customer`, `Lead`, `Package`, `Booking`, and `Payment` — but standalone, more complete versions of all 7 already existed as separate files (e.g. `Booking.php` at 101 lines vs. the bundle's ~40). Having two files declare the same class name would break Composer's classmap generation with a fatal "Cannot redeclare class" error.
**Fixed:** deleted `Models.php` (dead duplicate; nothing referenced it directly).

### 2.4 11 fatal calls to a nonexistent `->nullable()` relation method
Across `Proposal.php`, `QuotationItem.php`, `Quotation.php`, `Role.php`, `Communication.php`, `Task.php`, `Customer.php`, `Booking.php`, `AuditLog.php`, and `Lead.php`, `belongsTo()` relations were chained with `->nullable()`, which does not exist on Laravel's `BelongsTo` relation builder. This would fatal the instant any of these relationships were accessed or eager-loaded (e.g. `$customer->branch`, `->with('assignedTo')`).
**Fixed:** removed all 11 occurrences.

### 2.5 `str_slug()` — removed since Laravel 6 — called in the only registration path
In `AuthController::register()` and the unused `AuthService::register()`. Would fatal on every tenant/user registration.
**Fixed:** replaced with `Illuminate\Support\Str::slug()`.

### 2.6 `TenantMiddleware` applied a global `tenant_id` scope to every model unconditionally
Several tables (webhooks, api_settings, website_integration tables) have no `tenant_id` column — any query against them would throw `Unknown column 'tenant_id'`.
**Fixed:** the scope now checks `Schema::hasColumn()` (cached per-request) before applying the filter.

### 2.7 `RateLimitMiddleware` called `$request->user()->id`
`JwtAuth` middleware sets `$request->user` as a plain property, never authenticates through the guard — so `$request->user()` (the method) resolves to `null`, and `->id` would fatal on every rate-limited request.
**Fixed:** reads `$request->user` (the property), matching every other middleware in the stack.

### 2.8 Middleware alias mismatch: `api.key` vs. `api.key.auth`
`routes/api-webhooks-advanced.php` references the alias `api.key.auth`; a mismatched registration would 500 that entire route group.
**Fixed:** registered under the exact name the routes use.

### 2.9 `auth:sanctum` used, but `laravel/sanctum` was never a dependency
`routes/api-webhooks-advanced.php` gates its group behind `auth:sanctum`, but `composer.json` never required the package.
**Fixed:** added `laravel/sanctum` to `composer.json` and a `sanctum` guard to `config/auth.php`. **Requires `composer update` to actually install.**

### 2.10 `throttle:api` / `throttle:admin-api` named rate limiters were never defined
No `AppServiceProvider` existed anywhere in the project to register them; any request through those routes would throw `Rate limiter [api] is not defined`.
**Fixed:** created `app/Providers/AppServiceProvider.php` defining both, registered it in `bootstrap/providers.php`.

### 2.11 Every test file extends `Tests\TestCase`, which didn't exist
This alone made the claimed "89/89 tests passing" impossible — the suite could not have run.
**Fixed:** created `tests/TestCase.php`. Also added the `testing` (SQLite `:memory:`) DB connection that `phpunit.xml` expects but `config/database.php` (which didn't exist) never defined, and created the empty `tests/Unit/` directory `phpunit.xml` points to.

### 2.12 `User::hasPermission()` double-decoded already-cast JSON
`Role::permissions` is cast via `AsJson::class` (auto-decoded to an array), but `User::hasPermission()` called `json_decode()` on it again — `json_decode()` on an array returns `null`, silently making the method always return `false`. Not currently called by any live code path, but broken for whenever it is.
**Fixed:** removed the redundant decode.

### 2.13 Dead code noted, not removed
`app/Http/Controllers/Api/AuthController.php` is a second, unused `AuthController` with a case-mismatched namespace (`API` vs. folder `Api`) and no route references it. Left in place but flagged — recommend deleting in a future phase once you confirm nothing external depends on it.

---

## 3. What this phase did NOT do
No new features, no frontend, no AI/Telegram/i18n work — this phase was strictly "make the existing backend able to boot and route correctly." Per your directive's own gate, a phase isn't complete until tests actually run and pass — I could not run them here.

---

## 4. Verification — run these yourself

```bash
cd PROJECT
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret          # generates JWT_SECRET (tymon/jwt-auth)
# point .env at a real MySQL database, then:
php artisan migrate
php artisan serve               # confirm it boots and GET / responds
php artisan test                # or: vendor/bin/phpunit
```
If `composer install` or `php artisan test` surface further errors, they're genuinely new information — this audit was static and can't catch everything (e.g. actual SQL/migration ordering issues, package version conflicts).

## 5. Recommended next phase
**Phase 1 continued / Phase 2 prep:** run the verification commands above and report back what fails — real test output will surface anything this static pass missed. Once the backend is confirmed booting, Phase 2 (CRM core: deals, timeline, tags, segmentation) is next per your original roadmap — but given there's currently zero frontend, it's worth deciding whether frontend scaffolding should be pulled forward before Phase 2, since otherwise each backend phase keeps adding API surface nothing can exercise.
