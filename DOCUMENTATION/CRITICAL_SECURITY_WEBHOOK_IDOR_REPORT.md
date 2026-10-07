# QUDRIX CRM — CRITICAL SECURITY REPORT: Webhook Cross-Tenant IDOR

**তারিখ:** September 22, 2026
**Severity:** CRITICAL (P0) — cross-tenant data exposure এবং unauthorized cross-tenant write/delete
**আবিষ্কারের প্রেক্ষাপট:** Webhook admin UI বানানোর আগে backend audit করতে গিয়ে ধরা পড়েছে। UI বানানো শুরুই করা হয়নি যতক্ষণ না backend নিরাপদ প্রমাণিত হয়েছে।

## যা ছিল

তিনটা controller (`AdminWebhookController`, `WebhookMonitoringController`, `WebhookAnalyticsDashboardController`) — মোট প্রায় ২৫টা endpoint — `Webhook $webhook` route-model-binding দিয়ে সরাসরি ID থেকে webhook resolve করত, **কোনো tenant-ownership check ছাড়াই।** তিনটা route group-এর কোনোটাই `'tenant'` middleware ব্যবহার করত না, তাই `TenantMiddleware`-এর automatic global scope-ও কখনো কার্যকর হয়নি — যদিও `webhooks` টেবিলে নিজস্ব `tenant_id` কলাম (FK সহ) আছে।

**বাস্তব প্রভাব:** যেকোনো tenant-এর যেকোনো authenticated staff member — শুধু একটা webhook ID অনুমান/গণনা করে —
- অন্য tenant-এর webhook দেখতে পারত (URL, event config, delivery history, audit trail সহ)
- সেটা edit/delete করতে পারত
- Secret rotate করতে পারত (অন্য tenant-এর webhook signature validation ভেঙে দেওয়া)
- Test/toggle করতে পারত (অন্য tenant-এর integration ব্যাহত করা)
- Compliance report/audit log/security log পড়তে পারত

এছাড়া দুটো **secondary** issue পাওয়া গেছে একই audit-এ:
1. `AdminWebhookController@store` — `api_key_id` শুধু "exists" চেক করত, কোন tenant-এর সেটা তা চেক করত না — মানে একজন user অন্য tenant-এর API key-তে webhook attach করতে পারত।
2. `AdminWebhookController@retryDelivery` — route-এর `$webhook` parameter আসলে ব্যবহারই হতো না backend service-এ; `delivery_id` সরাসরি ব্যবহার হতো, তাই একজন user নিজের route-এর webhook ID ঠিক রেখেও অন্য tenant-এর `delivery_id` পাঠিয়ে তাদের delivery retry করাতে পারত।

## কেন এটা এতদিন ধরা পড়েনি

`TenantMiddleware`-এর কোড-কমেন্টে (Phase 1 audit থেকে) লেখা ছিল "webhooks... do not have a tenant_id column" — কিন্তু এটা এখন ভুল/পুরনো তথ্য। কোনো এক পরের ফেজে (webhook-এর "Advanced Features" ফেজ, নিজের ফাইলের কমেন্টেই "PHASE 3" উল্লেখ আছে) `webhooks` টেবিলে `tenant_id` যোগ হয়েছিল, কিন্তু middleware group-গুলো তখন `'tenant'` middleware যোগ করে আপডেট করা হয়নি। এটা ঠিক সেই ধরনের bug যা আগেও বারবার পাওয়া গেছে এই প্রজেক্টে (route registration miss, relation miss) — একটা অংশ পরিবর্তন হয়েছে, কিন্তু তার সাথে সংযুক্ত অন্য অংশ (এখানে: middleware) সমান্তরালে আপডেট হয়নি।

## ফিক্স

1. **`routes/api-public.php`**: `admin/api/webhooks` route group-এ `'tenant'` middleware যোগ
2. **`AdminWebhookController`**: প্রতিটা method-এ (যেগুলো `Webhook $webhook` নেয়) explicit `authorizeWebhook()` check — tenant না মিললে 404 (403 না, যাতে ID-এর অস্তিত্ব নিশ্চিত না হয়)। `store()`-এ api_key ownership check। `retryDelivery()`-এ delivery-webhook সম্পর্ক verify করা।
3. **নতুন middleware `EnsureWebhookBelongsToTenant`** (alias `webhook.tenant`) — `WebhookMonitoringController` (১০টা method) আর `WebhookAnalyticsDashboardController` (৯টা method)-এ প্রতিটা আলাদাভাবে ফিক্স করার বদলে, route parameter resolve হওয়ার পর একবারেই tenant check করে। দুটো route file-এই (`api-webhooks-monitoring.php`, `api-webhooks-advanced.php`) যোগ করা হয়েছে।

## যা ইচ্ছাকৃতভাবে touch করা হয়নি

`monitorAllWebhooks()`, `getSystemHealth()`, `getSummary()`-এর মতো aggregate/system-wide endpoint (যেগুলো কোনো নির্দিষ্ট `$webhook` নেয় না) — এগুলো সব tenant-এর ডেটা মিলিয়ে দেখানোর জন্যই বানানো হতে পারে (platform-level monitoring), তাই এটা আলাদা প্রশ্ন — এই নির্দিষ্ট, দ্ব্যর্থহীন IDOR fix-এর scope-এর বাইরে রাখা হয়েছে, backlog-এ নোট করা হলো।

## Regression

- Duplicate route/table: **PASS**
- Brace/paren balance (৬টা ফাইল): **PASS**
- একই ধরনের আর কোনো `Webhook`-related route group `'tenant'` ছাড়া আছে কিনা: **PASS** (আর নেই)

## ⚪ UNVERIFIED — Environment limitation

এই ফিক্স কোড-লেভেলে সঠিক বলে মনে হচ্ছে, কিন্তু **কোনো real PHP/MySQL পরিবেশে exploit করে বা করার চেষ্টা করে verify করা যায়নি।** এটা একটা security fix-এর জন্য সবচেয়ে গুরুত্বপূর্ণ ধরনের ফাঁক — production deploy করার আগে penetration-testing স্টাইলে verify করা জোরালোভাবে সুপারিশ করা হচ্ছে।

## এই ব্যাচে Webhook admin UI বানানো হয়নি

ইচ্ছাকৃতভাবে — একটা known-vulnerable backend-এর উপর UI বানানো বেঠিক অগ্রাধিকার হতো। পরের ব্যাচে UI বানানো হবে, এখন যে backend নিরাপদ (কোড-লেভেলে) সেটার উপর ভিত্তি করে।
