# QUDRIX CRM — CRITICAL FOLLOW-UP: Webhook Audit Tables Missing + Monitoring Aggregate Leak

**তারিখ:** September 25, 2026
**প্রেক্ষাপট:** Batch 13-এর security রিপোর্টে একটা follow-up item রাখা হয়েছিল: "aggregate/system-wide webhook monitoring endpoint যেগুলো নির্দিষ্ট webhook ID নেয় না, সেগুলোর cross-tenant aggregation নিয়ে প্রশ্ন এখনো খোলা"। এই ব্যাচে সেটা খুলে দেখতে গিয়ে চারটা আলাদা, গুরুত্বপূর্ণ জিনিস পাওয়া গেছে।

## ১. সবচেয়ে বড় finding: তিনটা টেবিলই কখনো তৈরি হয়নি

`webhook_audit_logs`, `webhook_delivery_audit_logs`, `webhook_security_audit_logs` — `WebhookAuditLoggingService`-এর প্রতিটা read/write method এগুলো ব্যবহার করে, কিন্তু **কোনো migration কখনো এই টেবিলগুলো তৈরিই করেনি।** মানে audit trail, security log, compliance report, log export — এই পুরো feature-সেট **প্রতিবার SQL "table not found" error দিয়ে crash করত।** এটা security-এর প্রশ্নের চেয়েও আগে — একটা সম্পূর্ণ ভাঙা feature, যা কোনো phase report-এই ধরা পড়েনি।

**ফিক্স:** নতুন migration দিয়ে তিনটা টেবিল তৈরি — এবং যেহেতু এই প্রথমবার টেবিল বানানো হচ্ছে, শুরু থেকেই `tenant_id` কলাম যোগ করা হয়েছে (নাহলে টেবিল বানানোর সাথে সাথেই #২-এর মতো leak আবার ফিরে আসত)।

## ২. Monitoring aggregate endpoint-এ real cross-tenant leak

`monitorAllWebhooks()` — `Webhook::where('is_active', true)->get()` — **কোনো tenant filter ছাড়া।** এটাই ব্যবহার করে `getAlerts()` আর `getDashboardSummary()`। মানে যেকোনো authenticated user "system-wide" dashboard/alert দেখতে গেলে **প্রতিটা tenant-এর webhook URL আর health status** দেখতে পেত — কোনো ID guess করারও দরকার ছিল না, শুধু endpoint hit করলেই।

**ফিক্স:** `monitorAllWebhooks()` এখন `$tenantId` বাধ্যতামূলকভাবে নেয় এবং সেই অনুযায়ী filter করে। `WebhookDelivery`-এর নিজস্ব কোনো `tenant_id` কলাম নেই বলে, delivery-সংক্রান্ত aggregate-ও এখন tenant-এর নিজস্ব webhook ID তালিকার মাধ্যমে scope করা হয়েছে।

**যা touch করা হয়নি:** `getSystemHealth()`/`getCachedHealth()` — এগুলো review করে দেখা গেছে শুধু aggregate count/rate রিটার্ন করে (কোনো URL/নাম/tenant-চেনা তথ্য না) — এটা genuinely platform-wide status-page ধরনের জিনিস, আগের `AdminApiKeyController`-এর মতোই বৈধ platform-level monitoring, তাই অপরিবর্তিত রাখা হয়েছে।

## ৩. নিজের batch-13 ফিক্সেই একটা latent bug ধরা পড়েছে

`AdminWebhookController` আর `WebhookMonitoringController` দুটোই `$request->user->tenant_id` সরাসরি ব্যবহার করছিল (batch 13-এর নিজের ফিক্স-সহ)। কিন্তু এই দুই controller-এর route group `'auth:api'`/`'auth'` middleware ব্যবহার করে — এই প্রজেক্টের নিজস্ব `'jwt.auth'` alias না, যেটা `$request->user` (custom property) সেট করে। মানে `auth:api`/`auth` guard দিয়ে authenticate হওয়া প্রতিটা request-এ `$request->user` আসলে **কখনোই সেট হতো না** — একটা null-property fatal error হতো প্রতিবার, exactly সেই security check-টাই যেটা আমি batch 13-এ যোগ করেছিলাম।

**ভালো খবর:** এটা data leak করত না — crash করত (fail-safe দিকে ভুল হয়েছিল, ঠিক দিকে না)। কিন্তু এটা তো একটা functional bug, নিজের করা ফিক্স নিজেই যাচাই করে ধরা দরকার ছিল।

**ফিক্স:** দুই controller-এই একটা shared `currentTenantId()` helper, যা `$request->user ?? auth()->user()` fallback ব্যবহার করে — ঠিক `TenantMiddleware`-এর নিজস্ব প্যাটার্ন অনুসরণ করে।

## ৪. আরেকটা ভাঙা (leak না, শুধু broken) feature

`WebhookAnalyticsDashboardController@getSummary` — `auth()->user()->webhooks` ব্যবহার করত, কিন্তু `User` মডেলে `webhooks()` নামে কোনো relation-ই নেই। মানে এটা সবসময় নীরবে খালি array রিটার্ন করত — leak না, কিন্তু চিরকাল ভাঙা।

**ফিক্স:** সরাসরি `Webhook::where('tenant_id', ...)` ব্যবহার করা হয়েছে, বাকি ফাইলের প্যাটার্নের সাথে সামঞ্জস্যপূর্ণভাবে।

## Regression

- Brace/paren balance (৬টা ফাইল): **PASS**
- Duplicate table/route (প্রজেক্ট-জোড়া পুনরায় চেক): **PASS**
- আরও কোনো unscoped `WebhookDelivery::where(...)` আছে কিনা: **PASS** (বাকি সব হয় webhook_id দিয়ে আগে থেকেই scoped, নয়তো maintenance-only/route-এ exposed না এমন method — `WebhookBatchingService::flushExpiredBatches()`, `purgeOldLogs()` — এগুলো background cleanup job, কোনো route নেই, তাই এই ব্যাচের scope-এর বাইরে রাখা হয়েছে, backlog-এ নোট)

## ⚪ UNVERIFIED — Environment limitation

এই পুরো ফিক্স-চেইন (নতুন টেবিল + tenant filtering + auth fallback) কোড-লেভেলে সঙ্গতিপূর্ণ মনে হচ্ছে, কিন্তু migration চালিয়ে, বাস্তব JWT দিয়ে request পাঠিয়ে — কোনোভাবেই verify করা যায়নি। বিশেষভাবে গুরুত্বপূর্ণ: migration-টা প্রথমবার চালানোর সময় সত্যিই সব foreign key (webhooks, users, tenants) resolve করবে কিনা।

## এখনো backlog-এ যা রইল

- `WebhookBatchingService::flushExpiredBatches()` আর `WebhookAuditLoggingService::purgeOldLogs()` — কোনো route/scheduled command-এ wired না, তাই আপাতত dead code; ভবিষ্যতে সত্যিই ব্যবহার করা হলে tenant-scoping পুনর্বিবেচনা দরকার হবে
