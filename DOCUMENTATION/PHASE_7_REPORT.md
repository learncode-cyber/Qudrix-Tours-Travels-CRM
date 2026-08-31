# Phase 7 Report — Telegram + Notification System

Like Phases 5/6, this module's backend (`NotificationController`,
`NotificationService`, `TelegramNotificationService`,
`ConversationController`, and their models/migrations) was built in a
prior session but had never actually been executed against a live
database, called with a real HTTP request, or covered by an automated
test. This phase's work was: (1) audit it statically, (2) live-test every
endpoint end-to-end for the first time, (3) fix the one real bug it
exposed, (4) write its automated test suite, (5) build its frontend, (6)
verify everything together.

## 1. What was implemented

- **Audit**: route-to-controller-method cross-reference (clean), plus a
  close read of `NotificationService::send()`,
  `TelegramNotificationService::send()`, and
  `ConversationController::attemptDelivery()` — all three are built
  around the same honesty principle (`NotificationService`'s own comment:
  "never a fabricated 'sent: true'"). Confirmed the design holds for
  every channel: `email` only ever reports `sent` after Laravel's mailer
  accepts it (no swallow-and-pretend), `telegram` only after Telegram's
  API returns success, an unconfigured/misconfigured channel always
  reports a specific `CONTRACT REQUIRED` or "no X configured" reason, and
  an internal note is never attempted at all.
- **Live backend verification**: ran the real end-to-end flow —
  created a customer, created a `telegram` conversation, replied to it and
  observed the honest `not_attempted` outcome, found and fixed the
  `external_thread_id` bug (below), replied again and observed the
  correctly different honest `failed: TELEGRAM_BOT_TOKEN is not
  configured` outcome, ran `recordInbound` (confirmed unread-count bump
  and closed→open reopening), `assign`, `updateStatus`, and the full
  notification lifecycle (`unread-count`, `read`, `read-all`), including
  triggering a real one via lead assignment and confirming its
  `telegram`-channel delivery attempt against a user's newly-set
  `telegram_chat_id`.
- **Frontend** (new, first UI this module has ever had):
  - `NotificationsPage.tsx` — list with an unread-only filter, mark
    read / mark all read.
  - `ConversationsPage.tsx` — the unified inbox: filterable list by
    channel and status, a create-conversation form (including
    `external_thread_id` — the exact field this phase's bug fix
    unblocked), and a thread modal showing every message with its
    per-message delivery status/error, a reply form (with an
    internal-note toggle), status-change buttons, and assign.
  - `ProfilePage.tsx` — the only place in this frontend a user can set
    their own `telegram_chat_id`, `name`, and `phone`. Nothing built this
    before this phase, so Telegram notifications had no way to be
    configured from the UI at all.
- **Automated tests**:
  `tests/Feature/Phase7NotificationsTelegramTest.php` — 15 tests, the
  first this module has ever had.

## 2. Files / modules changed

Backend:
- `app/Http/Controllers/ConversationController.php` — added
  `external_thread_id` to `store()`'s validation (see §8/9).
- `tests/Feature/Phase7NotificationsTelegramTest.php` (new)

Frontend:
- `frontend/src/pages/NotificationsPage.tsx` (new)
- `frontend/src/pages/ConversationsPage.tsx` (new)
- `frontend/src/pages/ProfilePage.tsx` (new)
- `frontend/src/api/endpoints.ts` — added `updateProfile` and wrappers
  for notifications and conversations
- `frontend/src/types/index.ts` — added `Notification`, `Conversation`,
  `ConversationMessage` and their status/channel union types
- `frontend/src/App.tsx` — added `/notifications`, `/conversations`,
  `/profile` routes
- `frontend/src/components/AppLayout.tsx` — added "Conversations",
  "Notifications", "Profile" nav entries

Documentation:
- `DOCUMENTATION/PHASE_7_REPORT.md` (this file)
- `DOCUMENTATION/API_DOCUMENTATION_COMPLETE.md` — Phase 7 addendum
- `CHANGELOG.md`, `PROJECT_STATUS.md` — updated

## 3. Database changes

None. `notifications`, `conversations`, and `conversation_messages`
already existed from the prior session's migrations, `external_thread_id`
included — the bug was a validation gap, not a missing column.

## 4. API changes

One: `POST /api/v1/conversations` now accepts `external_thread_id`
(nullable string, max 255) in addition to its previous fields. No other
route or contract changed. See
`DOCUMENTATION/API_DOCUMENTATION_COMPLETE.md`'s Phase 7 addendum for the
full endpoint reference.

## 5. Frontend changes

Three new pages and their routes/nav entries, described in §1. Reused
every existing shared component and utility rather than introducing new
ones.

## 6 & 7. Tests performed and results

**Automated (PASS):**
```
Tests:    160 passed (469 assertions)
Duration: 7.93s
```
(145 pre-existing + 15 new `Phase7NotificationsTelegramTest` tests, zero
failures, zero regressions.) The 15 new tests cover: notification
read/unread lifecycle and unread-count; mark-all-read; user- and
tenant-scoping of notifications; a real lead-assignment triggering an
actual `Notification` row; `telegram_chat_id` persisting through
`PUT /profile`; the customer-or-lead requirement on conversation
creation; `external_thread_id` persisting on create (the regression test
for this phase's fix); reply delivery-status correctness for a
telegram conversation with no chat id (`not_attempted`) and with one but
no bot token configured (`failed`, with the exact honest reason string);
internal notes never getting a delivery attempt on two different
channels; inbound messages bumping `unread_count` and reopening a closed
conversation; opening a conversation clearing its unread count; assign
and status-change; and tenant scoping of the conversations list.

**Manual/live (PASS):** Two full passes:
1. Direct curl calls with a real JWT against a live `php artisan serve`
   instance — created a customer, created a telegram conversation
   (initially without `external_thread_id`, confirming the pre-fix bug
   live before fixing it), replied and observed `not_attempted`; after
   the fix, created a second conversation with a real chat id and
   observed the reply correctly *attempt* delivery and honestly report
   `failed: TELEGRAM_BOT_TOKEN is not configured` (no network in this
   sandbox); exercised `recordInbound`, `show` (unread-count clearing),
   `assign`, `updateStatus`, and the list with a channel filter; set a
   real `telegram_chat_id` via `PUT /profile`; triggered a real
   lead-assignment notification and confirmed it landed in
   `GET /notifications`; exercised `read`, `read-all`, and
   `unread-count`.
2. Headless-Chromium Playwright E2E against the built frontend served via
   `vite preview`, logged in as the real seeded admin: saved a Telegram
   chat id on the Profile page, opened the Notifications page, created a
   Telegram conversation with a real `external_thread_id` through the
   Conversations page's form, opened its thread, sent a reply, and
   confirmed the "Failed" delivery badge rendered with the honest reason
   — zero `pageerror` events. A separate full-app sweep (all
   pre-existing pages plus this phase's three new ones) also completed
   with zero page errors.

**Build/typecheck (PASS):** `npx tsc --noEmit` silent; `npm run build`
succeeded (121 modules, no errors).

## 8 & 9. Bugs discovered and fixed

1. **`POST /conversations` silently dropped `external_thread_id`.**
   `ConversationController::store()`'s validation whitelist
   (`customer_id`, `lead_id`, `channel`, `subject`, `assigned_to`) never
   included `external_thread_id`, even though the column has existed on
   `conversations` since its migration and
   `ConversationController::attemptDelivery()` depends on it entirely for
   the `telegram` case (and, by the same code path, any
   connector-mediated channel that keys off it as the send target).
   Practical effect: it was impossible, through the real API, to create a
   Telegram (or WhatsApp/SMS) conversation with an actual target chat
   id — every reply on such a conversation would permanently record
   `delivery_status: not_attempted, delivery_error: "No Telegram chat id
   stored on this conversation."`, regardless of what the caller
   intended. This is the same bug class found in Phase 4
   (`VisaController::store` dropping `embassy_id`) — a real,
   schema-backed field silently absent from a validation array. Caught
   live-testing the exact workflow a real support agent would follow:
   create a conversation for a known Telegram contact, then reply.
   **Fixed** by adding `'external_thread_id' => 'nullable|string|max:255'`
   to the validation.

No other bugs found — every other endpoint in this module behaved
exactly as its code describes on first live execution.

## 10. Regression results

Full suite: 160/160 passing (was 145/145 before this phase — net +15,
zero failures introduced). Full-app Playwright sweep across all
pre-existing pages plus this phase's three new ones: zero `pageerror`
events. The one code change (`ConversationController::store()`'s
validation) only widens what was previously silently rejected — no
existing behavior for callers that never sent `external_thread_id`
changed.

## 11. Security considerations

- `TelegramNotificationService`'s bot token is read only from
  `config('services.telegram.bot_token')` — server-side configuration,
  never exposed to the frontend, matching the directive's security rule.
- Notifications and conversations were already correctly scoped by
  `tenant_id` (and, for notifications, additionally `user_id`) — verified
  by this phase's new scoping tests, not just assumed from the code.
- No new secrets, API keys, or credentials introduced. `.env.example`
  already documents `TELEGRAM_BOT_TOKEN` as the variable to set in a real
  deployment (this phase didn't need to add it — it was already there
  from the prior session).

## 12. Known limitations

- **Telegram message delivery itself is UNVERIFIED** — this sandbox has
  no outbound network, so the only thing verified live is the *honest
  failure path* when no bot token is configured. The code path that
  would call the real Telegram Bot API on a configured token was not
  exercised against Telegram's actual servers. This mirrors the
  project's standing, previously-documented limitation for this phase —
  not a new gap introduced here.
- **No lead/customer picker in the Conversations "New Conversation"
  form** — plain numeric ID fields with a hint, since no
  lead/customer-search endpoint is wired into this frontend yet (same
  limitation noted in the Phase 6 report for the Package Builder's lead
  field).
- **"Assign" in the conversation thread modal uses a plain `prompt()`
  for a user ID** rather than a picker, for the same reason — no
  user-directory endpoint wired into this frontend.
- Same cross-cutting limitations as every prior phase (RBAC not
  route-enforced, MySQL-only in production/SQLite-only verified here) —
  see `PROJECT_STATUS.md`.

## 13. UNVERIFIED items

- Real Telegram message delivery against a live bot token (no outbound
  network in this sandbox) — see §12.
- Real email delivery for the `email`-channel conversation reply path —
  this sandbox has no configured mail transport; `Mail::raw()` was not
  exercised against a live mailer this phase.
- Production MySQL 8.0+ behavior — verified here against SQLite only.
- RBAC-level authorization on these endpoints — same cross-cutting,
  already-documented gap from Phase 1, not new to this phase.

## 14. Deployment instructions

Identical pattern to every prior phase, plus setting the Telegram bot
token to actually enable delivery in production:
```
composer install --no-dev --optimize-autoloader
# configure .env: DB_CONNECTION, DB_DATABASE (MySQL 8.0+ in production),
# and TELEGRAM_BOT_TOKEN (see .env.example) to enable real Telegram delivery
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
php artisan test --filter=Phase7NotificationsTelegramTest
# expect: Tests: 15 passed (44 assertions)

php artisan test
# expect: Tests: 160 passed (469 assertions)

# live curl (after login, with a Bearer token and a real customer/lead):
curl -X POST /api/v1/conversations -d '{"customer_id":1,"channel":"telegram","external_thread_id":"123456789"}'
# expect: 201, response data includes "external_thread_id":"123456789"

curl -X POST /api/v1/conversations/<id>/reply -d '{"body":"test"}'
# expect (no TELEGRAM_BOT_TOKEN configured): 201, delivery_status "failed",
# delivery_error "CONTRACT REQUIRED: TELEGRAM_BOT_TOKEN is not configured"

cd frontend && npx tsc --noEmit && npm run build
# expect: build succeeds, typecheck silent
```
