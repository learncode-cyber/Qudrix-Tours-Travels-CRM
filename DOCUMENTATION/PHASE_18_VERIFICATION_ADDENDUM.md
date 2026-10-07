# QUDRIX Travel CRM — Phase 18 ভেরিফিকেশন অ্যাডেন্ডাম

**তারিখ:** September 18, 2026
**Environment note (অপরিবর্তিত):** এই sandbox-এ PHP/MySQL/network নেই, তাই migration সরাসরি চালিয়ে দেখা যায়নি — নিচের সবকিছু কোড-রিভিউ ও static analysis (grep/স্ক্রিপ্ট দিয়ে ক্রস-চেক)।

## 1. সবচেয়ে গুরুতর ফাইন্ডিং: তিনটা route ফাইল কখনোই লোড হতো না

`bootstrap/app.php`-এর `withRouting()`-এ শুধু `api.php`, `web.php`, `console.php` পাস করা ছিল। কিন্তু নিচের তিনটা ফাইল — যেগুলোর প্রতিটাতেই সম্পূর্ণ controller, middleware, এমনকি নিজস্ব test suite পর্যন্ত আছে — কোথাও `require`/register করা ছিল না, ফলে বাস্তবে বুট করলে এগুলোর প্রতিটা route 404 দিত:

- `routes/api-public.php` — public package listing, booking, quotation endpoints (`Api\PublicPackageController`, `Api\PublicBookingController`, `Api\PublicQuotationController`), admin API-key management (`Admin\AdminApiKeyController`), admin webhook CRUD (`Admin\AdminWebhookController`)
- `routes/api-webhooks-advanced.php` — webhook analytics dashboard (`Admin\WebhookAnalyticsDashboardController`)
- `routes/api-webhooks-monitoring.php` — health/monitoring/audit endpoints (`Admin\WebhookMonitoringController`)

`tests/Api/PublicApiTest.php`, `WebhookTest.php`, `WebhookAdvancedFeaturesTest.php`, `WebhookMonitoringTest.php` — এই টেস্টগুলো ঠিক এই route-গুলোই হিট করে, তাই এই bug নতুন কিছু ভাঙেনি বরং শুরু থেকেই এই পুরো feature-সেট অকার্যকর ছিল।

**ফিক্স:** `withRouting()`-এ একটা `then` closure যোগ করে তিনটা ফাইলকেই `require` করা হয়েছে। `then` কোনো implicit prefix/middleware চাপায় না, তাই প্রতিটা ফাইলের নিজস্ব `Route::prefix()->middleware()` অবিকৃত থাকে। (এগুলোকে `api.php`-এর ভেতর থেকে require করলে ভুল হতো — তাহলে Laravel-এর automatic `/api` prefix তাদের নিজস্ব prefix-এর উপর দ্বিতীয়বার বসে যেত, যেমন `admin/api/webhooks-advanced` হয়ে যেত `/api/admin/api/webhooks-advanced`।)

## 2. Dead code: একটা wrongly-namespaced, tenant-scoping-বিহীন duplicate AuthController

`app/Http/Controllers/Api/AuthController.php` — namespace ছিল `App\Http\Controllers\API` (ভুল কেসিং, ফোল্ডার হলো `Api`), কোথাও route করা ছিল না, ভেতরে `JWTAuth::attempt()` সরাসরি কোনো tenant check ছাড়াই। এটা ঠিক Phase 18-এর মূল রিপোর্টে পাওয়া orphaned `TenantController`-এর মতোই প্যাটার্ন — যদি কখনো ভুলে route করা হতো, tenant isolation বাইপাস হয়ে যেত। সম্পূর্ণ মুছে ফেলা হয়েছে; আসল, সঠিক `App\Http\Controllers\AuthController` (register/login/logout/profile, tenant-aware) অক্ষত ও route করা আছে।

## 3. Dead feature: tenant self-service API key management-এর কোনো route-ই ছিল না

`app/Http/Controllers/Api/ApiKeyController.php` (index/store/show/update/revoke/destroy/logs/stats) সম্পূর্ণ বানানো ছিল, এবং এর `ApiKeyPolicy` Phase 15 security audit-এ registered ও fix করা হয়েছিল ("fail closed" bug) — কিন্তু কোনো route file-এ (এমনকি এখন-fix-হওয়া তিনটা ফাইলেও না) এটার একটাও route ছিল না। ফলে built + audited + policy-protected একটা সম্পূর্ণ feature কোনো tenant-ই কখনো ব্যবহার করতে পারত না।

**ফিক্স:** `routes/api.php`-এ Phase 18 block-এর পরে `/api-keys` prefix-এ নতুন route group যোগ করা হয়েছে (`jwt.auth`, `tenant`, `audit` middleware সহ, বাকি সব tenant route-এর মতোই)। `/logs` ও `/stats` কে `/{apiKey}` wildcard-এর আগে বসানো হয়েছে যাতে সেগুলো id হিসেবে match না হয়ে যায়।

## 4. যা আবার যাচাই করা হয়েছে এবং ঠিক পাওয়া গেছে (নতুন করে ভাঙা হয়নি)

- Duplicate `Schema::create()` টেবিল নাম: শূন্য (Phase 18-এর মূল ফিক্স অক্ষত)
- `Schema::table()` দিয়ে alter করা টেবিল যেগুলোর কোনো `Schema::create()` নেই: শূন্য
- Route name collision (`->name()`): শূন্য
- Controller namespace বনাম ফোল্ডার path case-mismatch: শুধু আইটেম #2 (এখন মোছা হয়েছে)
- `config/auth.php`-এর `api`/`sanctum` guard wiring: ঠিক আছে (আগের ফেজেই ফিক্স হয়েছিল)

## 5. Verified / Unverified / Blocked

**VERIFIED:** উপরের তিনটা ফিক্সই কোড-রিভিউ করে সঠিক এবং pre-existing test suite-এর প্রত্যাশার সাথে সামঞ্জস্যপূর্ণ।
**UNVERIFIED (অপরিবর্তিত, Phase 18 থেকেই):** real migration execution — sandbox-এ PHP/MySQL না থাকায় ১৮ ফেজ জুড়ে জমে থাকা migration-গুলো একসাথে চালিয়ে দেখা এখনো বাকি। এই zip প্রথমবার PHP/MySQL থাকা কোনো পরিবেশে (local বা staging) `composer install && php artisan migrate:fresh && php artisan test` চালানো জরুরি — এটাই real bug ধরার সবচেয়ে নির্ভরযোগ্য উপায়।
**BLOCKED (অপরিবর্তিত):** পূর্ণাঙ্গ billing/payment-gateway integration (real credential নেই)।

## 6. প্রজেক্টের সামগ্রিক অবস্থা (২০ ফেব্রুয়ারি সেশনের প্রশ্নের উত্তরে)

- Backend: ১৮টা ফেজ সম্পন্ন — CRM core, Finance, Notification, Reporting, Webhook system (advanced + monitoring + analytics), AI provider abstraction layer (credential ছাড়াই interface হিসেবে), Automation, Pricing/Package builder, SEO/attribution, Multi-tenant SaaS readiness (tenant branding, subscription plans)।
- যা এখনো বাকি/অনির্ধারিত:
  - **Phase 19 (প্রস্তাবিত পরবর্তী):** Billing/Subscription Architecture — real payment gateway integration ছাড়া সম্ভব না, credential দরকার।
  - **Frontend:** `FRONTEND/` ফোল্ডারে scaffolding আছে (React + TS + Vite), কিন্তু backend-এর ১৮ ফেজের API surface-এর তুলনায় frontend build সম্পূর্ণ কিনা তা এই sandbox থেকে যাচাই করা যায়নি (npm/node নেই এই পরিবেশে) — এটা একটা আলাদা ভেরিফিকেশন ব্যাচ হিসেবে সুপারিশ করছি।
  - **Real migration + test run** — উপরে #5-এ উল্লেখিত, সবচেয়ে গুরুত্বপূর্ণ পরবর্তী ধাপ।
- কোনো নতুন module scope চূড়ান্ত করা হয়নি — Phase 19 বা frontend batch, কোনটা আগে করা হবে সেটা আপনার সিদ্ধান্ত।

## 7. Verification

```bash
cd PROJECT
composer install && cp .env.example .env
php artisan key:generate && php artisan jwt:secret
php artisan migrate:fresh
php artisan serve

# আগে অকার্যকর ছিল, এখন কাজ করা উচিত:
curl localhost:8000/api/v1/packages -H "X-API-Key: <key>" -H "X-API-Secret: <secret>"
curl -X POST localhost:8000/api/v1/api-keys -H "Authorization: Bearer <TOKEN>" -d '{"name":"test"}'
curl localhost:8000/admin/api/webhooks-monitoring/health/system

php artisan test
```
