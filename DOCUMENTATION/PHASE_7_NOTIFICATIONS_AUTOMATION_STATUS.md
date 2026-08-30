# Phase 7 (Master Directive numbering) — Telegram + Notifications + Automation

**Date:** 2026-08-30
**Scope:** Directive §28 Phase 7 / §16–17 (Automation Engine, Telegram/Notification System), continuing
from Phase 6.

## The most serious "fake functionality" violation found so far

`app/Services/AutomationEngine.php` — the engine every configured automation actually runs through —
had every single action handler return a **hardcoded fabricated success** with no real effect:

- `sendEmail()` returned `['sent' => true]` without calling `Mail` at all.
- `sendSms()` returned `['sent' => true]` with no SMS provider anywhere in the codebase.
- `createTask()` returned `['created' => true]` without ever calling `Task::create()`.
- `updateCustomer()` returned `['updated' => true]` without touching the `Customer` model.
- `createNotification()` returned `['created' => true]` — and the `Notification` model it claimed to
  create was, project-wide, never instantiated anywhere (confirmed via grep: zero hits for
  `Notification::create` or `new Notification` before this phase).
- `callWebhook()` returned `['status' => 'called']` without making any HTTP request.

This is exactly what Directive §37 forbids: *"If a button says 'Send' → it must actually send... No fake
functionality."* Every admin who configured an automation step in this system was being lied to about
what happened. All six handlers are now rewritten to perform the real action (or, for SMS, to honestly
report that no provider is configured — see below) and return the real outcome, success or failure.

## What Phase 7 built

- **`TelegramNotificationService`** — a real client for Telegram's public Bot API (`sendMessage`), using
  `config('services.telegram.bot_token')` (server-side only, from Phase 0's `config/services.php`). This
  is not an invented endpoint — Telegram's Bot API is the well-known documented contract the directive
  names explicitly (§17) — but it is genuinely unusable until an operator sets `TELEGRAM_BOT_TOKEN`.
  Without it, `send()` returns `sent: false` with the reason, never a fake success.
- **`NotificationService`** — fans a notification out to `in_app` (always, via the now-actually-used
  `Notification` model), `telegram` (if the target user has opted in via a new `telegram_chat_id` field —
  added to `users`, settable via the new `PUT /api/v1/profile`), and `email` (via `Mail::raw`, real
  attempt with real failure handling, not a blind `sent: true`).
- **`AutomationEngine`** rewritten: `send_email` really emails, `create_task` really creates a `Task` row
  (tenant-scoped from the automation's own `tenant_id`), `update_customer` really updates the named
  `Customer` field (validated against the model's fillable list first — refuses unknown fields rather than
  mass-assigning blindly), `create_notification` really calls `NotificationService`, `webhook` really
  issues an HTTP POST via `Http::post()` and reports the actual status code. `send_sms` is honestly
  reported as `CONTRACT REQUIRED` — no SMS provider was specified anywhere in this project's
  configuration or requirements, so nothing is invented for it (Directive §18/§35).
- **Wired real triggers** matching the directive's own examples: `LeadController@store`/`assignLead` now
  notify the assigned salesperson (in-app + Telegram) on lead creation/(re)assignment; `BookingController@store`
  notifies the creating staff member on booking creation.
- **`NotificationController`** — list (with unread filter), mark-read, mark-all-read, unread count.

## Verification

Same constraint as prior phases — no `vendor/`, no DB, no Packagist/Telegram network access in this
session, so the actual Telegram/email/webhook HTTP calls are **UNVERIFIED** (code reviewed against the
documented Telegram Bot API and Laravel's `Http`/`Mail` facades, but not executed). `php -l` passes with
zero errors across `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `tests/`, `resources/`; every
controller referenced in `routes/api.php` confirmed to exist on disk. `composer install` / `artisan
migrate` / `artisan test` remain `UNVERIFIED — REQUIRES LOCAL/STAGING EXECUTION`.
