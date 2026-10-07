# QUDRIX Travel CRM — PHASE 16 রিপোর্ট
## SEO + Website Analytics

**তারিখ:** September 2, 2026
**Environment note (অপরিবর্তিত):** এই sandbox-এ PHP/MySQL/network নেই।

---

## 1. এই ফেজের সবচেয়ে গুরুত্বপূর্ণ ঘটনা: একটা duplicate তৈরি করে ফেলা এবং সেটা শোধরানো
Audit শুরুতে WebsiteIntegration মডেল পাওয়া গেল যার কোনো controller বা route ছিল না - সম্পূর্ণ অব্যবহৃত। আমি প্রথমে ধরে নিয়েছিলাম এটা সম্পূর্ণ নতুন বানাতে হবে, এবং একটা WebsiteIntegrationController লিখেও ফেলেছিলাম। রুট wire করার সময় দেখলাম routes/api-public.php-তে ইতিমধ্যে একটা সম্পূর্ণাঙ্গ Admin\IntegrationController + IntegrationService আছে যা এই একই কাজ করে। আমার লেখা duplicate controller মুছে ফেলেছি এবং বিদ্যমান, বেশি সম্পূর্ণ কোডটাকেই ঠিক করেছি।

## 2. যে দুটো বাগের কারণে Admin\IntegrationController সম্পূর্ণ অকার্যকর ছিল
- 'role' middleware alias কখনো registered ছিল না - controller-এর constructor-এ role:super-admin,admin কল করা হতো, কিন্তু bootstrap/app.php-তে এই alias ছিল না। ফিক্স: একটা real RoleMiddleware বানিয়ে alias রেজিস্টার করা হয়েছে।
- এই controller auth:api (Laravel built-in guard, আমাদের কাস্টম jwt.auth না) ব্যবহার করে, যা tymon/jwt-auth-এর guard driver দিয়ে প্রকৃতপক্ষে Auth সিস্টেমে ইউজার populate করে - তাই এখানে auth()->user() আসলে কাজ করে। এটা যাচাই করে নিশ্চিত হয়েছি, ভুল অনুমান করে অহেতুক ফিক্স করিনি।

## 3. একটা critical security bug: plaintext credential storage (সম্ভাব্য)
setCredentialsAttribute() মিউটেটর শুধু 'credentials' নামক key দিয়ে mass-assign করলে ট্রিগার হতো - কিন্তু $fillable-এ 'credentials' কখনো ছিলই না, crm_api_key/crm_api_secret/webhook_secret সরাসরি fillable ছিল। মানে এনক্রিপশন মিউটেটর কখনোই ট্রিগার হতে পারত না - এই তিনটা ফিল্ড plaintext আকারে সেভ হয়ে যেত। ফিক্স: Laravel-এর native encrypted cast ব্যবহার করা হয়েছে।

## 4. আমার নিজের ফিক্স থেকেই তৈরি হওয়া একটা bug - ধরা পড়েছে এবং ঠিক করা হয়েছে
WebsiteIntegration মডেল ঠিক করার পর, IntegrationService-এ তিনটা জায়গায় পুরনো ম্যানুয়াল Crypt::encryptString() কল ছিল - এগুলো এখন model-এর নতুন cast-এর সাথে মিলে ডবল-এনক্রিপশন ঘটাত। এবং getWebhookUrl()-এ getDecryptedWebhookSecret() কল ছিল - যে মেথডটাই model থেকে সরানো হয়েছিল, fatal error দিত। চারটা জায়গাই ঠিক করা হয়েছে।

## 5. Phase 16-এর নতুন কাজ
- SEO Metadata - entity-agnostic, Tags/Custom-Fields-এর মতো reusable প্যাটার্নে।
- Public metadata lookup + real fallback (Package ডেটা থেকে)।
- Sitemap/robots.txt - সম্পূর্ণ real, active Package/Destination থেকে জেনারেট।
- UTM/Campaign attribution - leads টেবিলে UTM কলাম, PublicQuotationController-এ ক্যাপচার, real attribution রিপোর্ট।
- Google Analytics/GTM - frontend-side script (Phase 17), backend শুধু tracking ID স্টোর করতে পারে (বিদ্যমান SettingController দিয়ে সম্ভব)।

## 6. Regression check
পুরো fake-metric sweep, controller/route resolution, model-to-table cross-check, 'role' middleware alias resolve, এবং routes/api-public.php-তে নিজের করা একটা route-nesting ভুল (respond রুট ভুল group-এ ঢুকে গিয়েছিল) নিজেই ধরে ঠিক করা হয়েছে।

## 7. Verified / Unverified / Blocked

VERIFIED: সব ফিক্স কোড-রিভিউ করে সঠিক পাওয়া গেছে।
UNVERIFIED: testConnection() (বাহ্যিক HTTP কল), real migration execution।
BLOCKED: কিছু নেই নতুন।

## 8. Verification

```bash
cd PROJECT
composer install && cp .env.example .env
php artisan key:generate && php artisan jwt:secret
php artisan migrate:fresh
php artisan serve

curl -X POST localhost:8000/admin/api/integrations -H "Authorization: Bearer <ADMIN_TOKEN>" -H "Content-Type: application/json" \
  -d '{"name":"Main site","website_url":"https://example.com","crm_base_url":"https://crm.example.com/api"}'

php artisan tinker
>>> \App\Models\WebsiteIntegration::first()->getRawOriginal('crm_api_key');
>>> \App\Models\WebsiteIntegration::first()->crm_api_key;

curl localhost:8000/api/v1/seo/sitemap.xml
```

## 9. পরবর্তী ফেজ
Phase 17 (Complete Frontend + Production Release) - এখন পর্যন্ত সব ব্যাকএন্ড, কোনো frontend নেই। বড় স্কোপ - ছোট অংশে ভাগ করে প্রস্তাব দেব।
