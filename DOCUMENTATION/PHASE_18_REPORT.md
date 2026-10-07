# QUDRIX Travel CRM — PHASE 18 রিপোর্ট
## Multi-tenant SaaS Readiness

**তারিখ:** September 2, 2026
**Environment note (অপরিবর্তিত):** এই sandbox-এ PHP/MySQL/network নেই।

---

## 1. সবচেয়ে গুরুতর ফাইন্ডিং: cross-tenant data leak হতে পারত এমন একটা orphaned controller
app/Http/Controllers/Api/TenantController.php - কোনো route কোথাও ছিল না, কিন্তু ভেতরে ছিল Tenant::paginate(15) কোনো tenant scoping ছাড়াই। যদি এটা কখনো route করা হতো, যেকোনো tenant-এর staff অন্য সব tenant-এর নাম/ইমেইল/স্ল্যাগ দেখতে পারত - একটা multi-tenant SaaS-এর সবচেয়ে গুরুতর সম্ভাব্য লঙ্ঘন। এর সাথে একই namespace App vs ফোল্ডার Api কেসিং-বাগও ছিল (Phase 1-এ dead AuthController-এ যেটা দেখেছিলাম)। সম্পূর্ণ সরিয়ে ফেলে, একটা নতুন, tenant-scoped TenantController বানানো হয়েছে।

## 2. দুটো টেবিল দুইবার করে সংজ্ঞায়িত ছিল - migration ক্র্যাশ করাত
Migration ordering যাচাই করার সময় ধরা পড়ল: webhooks এবং webhook_logs - দুটোই দুটো ভিন্ন migration ফাইলে Schema::create() করা ছিল, সম্পূর্ণ ভিন্ন schema নিয়ে। php artisan migrate চালালে দ্বিতীয়টা "table already exists" এরর দিয়ে পুরো migration ব্যাচ ব্যর্থ করে দিত।

প্রতিটার ক্ষেত্রে, real Eloquent মডেল (Webhook, WebhookLog) কোন schema আসলে ব্যবহার করে তা $fillable মিলিয়ে যাচাই করে সিদ্ধান্ত নেওয়া হয়েছে - উভয় ক্ষেত্রেই api_settings_table.php-এর ভেতরে bundled ভার্সনটাই real/ব্যবহৃত ছিল, আর 2024_08_17 তারিখের আলাদা ফাইলগুলো dead duplicate ছিল। দুটো dead duplicate migration ফাইল মুছে ফেলা হয়েছে। webhook_deliveries real এবং ব্যবহৃত - অক্ষত রাখা হয়েছে।

## 3. এই ফেজের নতুন কাজ
- Tenant branding/language - logo_url, primary_color, language কলাম যোগ।
- SubscriptionPlan - real মডেল/টেবিল, feature-entitlement JSON সহ। Tenant.plan (string) সরানো হয়নি backward compatibility-র জন্য, plan_id (real FK) পাশাপাশি যোগ করা হয়েছে।
- TenantController (পুনর্লিখিত) - show/update (নিজের tenant profile), plans (public catalog)।

## 4. Data isolation audit
প্রতিটা migration স্ক্যান করে দেখা হয়েছে কোন টেবিলে tenant_id নেই। প্রায় সবগুলোই legitimate child-table (parent টেবিলের মাধ্যমে scope হয়)। subscription_plans ইচ্ছাকৃতভাবে tenant-independent।

## 5. যা এই ফেজে বানানো হয়নি, এবং কেন
- পূর্ণাঙ্গ billing/payment-gateway integration - পরবর্তী আলাদা ফেজের বিষয়, কোনো real payment gateway credential নেই।
- Tenant onboarding wizard (multi-step UI) - register() ইতিমধ্যে single-step API endpoint হিসেবে আছে; multi-step UI frontend-এর কাজ।

## 6. Regression check
পুরো fake-metric sweep, controller/route resolution, model-to-table cross-check, এবং পুরো migrations ডিরেক্টরিতে duplicate table definition-এর জন্য একটা নতুন সিস্টেমেটিক script চালানো হয়েছে।

## 7. Verified / Unverified / Blocked

VERIFIED: সব ফিক্স কোড-রিভিউ করে সঠিক।
UNVERIFIED: real migration execution - ১৮ ফেজ জুড়ে জমে থাকা প্রায় ২০টা migration ফাইল একসাথে চালিয়ে দেখা এখনো বাকি।
BLOCKED: পূর্ণাঙ্গ billing।

## 8. Verification

```bash
cd PROJECT
composer install && cp .env.example .env
php artisan key:generate && php artisan jwt:secret
php artisan migrate:fresh
php artisan serve

curl localhost:8000/tenant -H "Authorization: Bearer <TOKEN>"
curl localhost:8000/subscription-plans -H "Authorization: Bearer <TOKEN>"
```

## 9. পরবর্তী ফেজ
Phase 19 (Billing/Subscription Architecture) অথবা frontend-এর বাকি ব্যাচ।
