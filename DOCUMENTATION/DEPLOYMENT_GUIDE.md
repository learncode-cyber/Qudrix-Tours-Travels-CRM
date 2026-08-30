# Qudrix AI Travel CRM — Deployment Guide

**Applies to:** the state of `PROJECT/` on branch `claude/qudrix-travel-crm-master-0opkmx`.

> **Read this first.** Nothing in this repository has been executed. There is no `vendor/` directory and
> no database in the environment where this code was written, so `composer install`, `php artisan
> migrate`, and `php artisan test` have **never been run**. Every command below is written from the
> code as it stands; expect to fix small things on the first real run. See
> `VERIFICATION_CHECKLIST.md` for what is and is not verified.

## 1. Requirements

| Component | Version |
|---|---|
| PHP | 8.2+ (developed against 8.4) |
| Composer | 2.x |
| MySQL | 8.0+ (the analytics service uses `DATE_FORMAT`, a MySQL function) |
| Web server | Nginx or Apache with the document root at `PROJECT/public` |

PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `json`, `bcmath`, `fileinfo`, `dom` (dompdf).

## 2. Install

```bash
cd PROJECT
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan jwt:secret          # tymon/jwt-auth — required, the app will not authenticate without it
```

## 3. Environment

Edit `.env`. The variables that **must** be set before first run:

```
APP_KEY=            # written by key:generate
APP_URL=https://crm.yourdomain.com
DB_DATABASE= DB_USERNAME= DB_PASSWORD=
JWT_SECRET=         # written by jwt:secret
SEED_ADMIN_EMAIL=you@yourdomain.com
SEED_ADMIN_PASSWORD=            # leave blank and one is generated and printed once
ADMIN_URL_PATH=admin            # change this to move the admin path
CORS_ALLOWED_ORIGINS=https://yourwebsite.com
```

Optional integrations — all of these degrade honestly when unset (features report
`CONTRACT REQUIRED` rather than pretending to work):

```
TELEGRAM_BOT_TOKEN=             # Phase 7 notifications
AI_DEFAULT_PROVIDER=            # Phase 9; per-tenant credentials are set in the admin UI, not here
MAIL_MAILER=smtp MAIL_HOST= MAIL_PORT= MAIL_USERNAME= MAIL_PASSWORD=
ALLOW_PRIVATE_NETWORK_CONNECTORS=false   # keep false unless a provider is on your internal network
```

## 4. Database

```bash
php artisan migrate            # 33 migrations
php artisan db:seed            # roles + first tenant + administrator ONLY
```

`db:seed` deliberately creates **no demo customers, leads, bookings or revenue** — a dashboard showing
invented revenue is exactly the fake data the project brief forbids. To load demo data into a
non-production environment:

```bash
php artisan db:seed --class=Database\\Seeders\\Phase1Seeder
```

The seeder prints the generated admin password **once** if `SEED_ADMIN_PASSWORD` was blank. Change it
after first login.

## 5. Storage, build, permissions

```bash
php artisan storage:link
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

There is **no frontend build step** — this repository is the backend API only. The React/TypeScript
admin UI described in the project brief has not been built yet (see `GAP_ANALYSIS.md`).

## 6. Production configuration

```bash
php artisan config:cache
php artisan route:cache
```

Set `APP_ENV=production` and `APP_DEBUG=false`. With `APP_DEBUG=true` a stack trace can expose
credentials.

## 7. Queue and scheduler

The automation engine's `delay_seconds` step sleeps synchronously (see the note in
`AutomationEngine::executeSteps`). Run automations through a queue worker rather than a web request:

```bash
QUEUE_CONNECTION=database
php artisan queue:table && php artisan migrate
php artisan queue:work --tries=3
```

Cron entry for the scheduler:

```
* * * * * cd /path/to/PROJECT && php artisan schedule:run >> /dev/null 2>&1
```

## 8. Web server

Point the document root at `PROJECT/public`. Nginx:

```nginx
root /var/www/qudrix/PROJECT/public;
index index.php;
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    include fastcgi_params;
}
```

## 9. HTTPS

Terminate TLS at the web server (Let's Encrypt or your own certificate). The app already sends
`Strict-Transport-Security` via `SecurityHeaders`, which is applied to the whole `api` middleware group
— that header is only meaningful over HTTPS.

## 10. Post-deploy smoke test

```bash
curl -s https://crm.yourdomain.com/api/v1/health
curl -s -X POST https://crm.yourdomain.com/api/v1/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"you@yourdomain.com","password":"..."}'
```

A successful login returns a JWT. Use it as `Authorization: Bearer <token>` for every other endpoint.

## 11. Troubleshooting

| Symptom | Cause |
|---|---|
| `Class "DatabaseSeeder" not found` | You are on a commit before Phase 19 |
| 401 on every protected route | `JWT_SECRET` not set — run `php artisan jwt:secret` |
| `Unknown column 'tenant_id'` | You are on a commit before Phase 15 (the old global scope) |
| `Base table or view already exists: webhooks` | Before Phase 12 (duplicate migrations) |
| `Table 'customers' doesn't exist` | Before Phase 12 (nine core tables had no migration) |
| 429 on login | Working as intended — 5 attempts/minute, then a 15-minute lockout |
| AI endpoints return 502 | No active AI provider configured, or its credentials are wrong |
| Campaign recipients all `skipped` | No provider connector configured for that channel |
