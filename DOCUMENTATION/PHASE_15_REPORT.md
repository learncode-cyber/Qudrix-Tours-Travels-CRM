# QUDRIX Travel CRM — PHASE 15 রিপোর্ট
## Security + Audit + Hardening

**তারিখ:** September 2, 2026
**Environment note (অপরিবর্তিত):** এই sandbox-এ PHP/MySQL/network নেই। composer audit চালানো সম্ভব হয়নি - নিচে UNVERIFIED হিসেবে চিহ্নিত।

---

## 1. এই ফেজে পাওয়া নতুন সমস্যা এবং ফিক্স

### 1.1 Brute-force protection - /login ও /register-এ কোনো rate limiting ছিল না
কেউ unlimited password-guessing চেষ্টা করতে পারত। ফিক্স: throttle:5,1 (প্রতি মিনিটে ৫টা চেষ্টা প্রতি IP) যোগ করা হয়েছে।

### 1.2 authorize('admin') - কোনো Gate কখনো define করা হয়নি
AdminController-এর ৫টা মেথডেই authorize('admin') কল ছিল, কিন্তু গোটা প্রজেক্টে কোথাও Gate::define('admin', ...) ছিল না। Laravel-এর authorize() কোনো ability resolve করতে না পারলে সবসময় deny করে - মানে এই endpoints গুলো যেকোনো ব্যবহারকারীর জন্য, এমনকি প্রকৃত super-admin-এর জন্যও, সবসময় 403 দিত। ফিক্স: AppServiceProvider::boot()-এ Gate::define('admin', ...) যোগ করা হয়েছে, বিদ্যমান User::hasPermission() দিয়ে চেক করে।

### 1.3 ApiKeyController - একই সমস্যা, ApiKeyPolicy কখনো ছিলই না
authorize('view'/'update'/'delete', $apiKey) চারবার ব্যবহৃত, কিন্তু কোনো Policy ক্লাস কখনো তৈরিই হয়নি। ফিক্স: একটা real ApiKeyPolicy বানানো হয়েছে (tenant-scoped) এবং রেজিস্টার করা হয়েছে।

### 1.4 AuditMiddleware - একটা off-by-one bug audit trail-কে ভুল ডেটা রেকর্ড করাচ্ছিল
routes/api.php স্বয়ংক্রিয়ভাবে /api prefix পায়, তাই বাস্তব path হয় api/leads/5। কিন্তু getEntityType()/getEntityId() ভুল ইনডেক্স পড়ছিল - entity_type-এ numeric ID রেকর্ড হচ্ছিল, entity_id সবসময় null থাকছিল। ফিক্স: সঠিক ইনডেক্স ব্যবহার করা হয়েছে।

### 1.5 AuditMiddleware::$skipPaths - leading slash থাকায় skip-লজিক কখনো কাজ করত না
Request::is() leading slash ছাড়া path তুলনা করে। ফিক্স: leading slash সরানো হয়েছে।

### 1.6 Password policy দুর্বল ছিল
শুধু min:8। ফিক্স: Laravel-এর built-in Password rule (mixed case + সংখ্যা + symbol) ব্যবহার করা হয়েছে।

## 2. যাচাই করে ঠিক পাওয়া গেছে (নতুন বাগ নয়)
- DatabaseOptimizer - টেবিলের নাম SHOW TABLES থেকে আসে, ইউজার-ইনপুট থেকে না। SQL injection ঝুঁকি নেই।
- logout() - সঠিকভাবে JWTAuth::invalidate() কল করে।
- CSRF - সম্পূর্ণ stateless JWT-ভিত্তিক অ্যাপ, cookie/stateful middleware সক্রিয় নেই।

## 3. যা পাওয়া গেছে কিন্তু ঠিক করা হয়নি - গ্যাপ হিসেবে চিহ্নিত
- Password reset endpoint - broker config আছে কিন্তু কোনো controller ব্যবহার করে না।
- CORS allowed_origins ডিফল্ট '*' - .env দিয়ে override করা যায়, production-এ নির্দিষ্ট ডোমেইনে সীমাবদ্ধ করার পরামর্শ।
- Dependency vulnerabilities (composer audit) - network না থাকায় UNVERIFIED।
- AuthService.php-এর dead-code register()-এ একই দুর্বল rule - dead code বলে ছোঁয়া হয়নি।

## 4. কিউমুলেটিভ বাগ সারাংশ (Phase 1 থেকে 15)

| ফেজ | সবচেয়ে গুরুত্বপূর্ণ ফাইন্ডিং |
|---|---|
| 1 | পুরো Laravel framework skeleton অনুপস্থিত ছিল |
| 2 | Customer/Lead-সহ ১০টা core টেবিলের migration কখনো ছিলই না |
| 3 | Public API auth claim করা হলেও বাস্তবে enforced ছিল না |
| 4 | 'datetime' নামক অস্তিত্বহীন validation rule, crash হতো |
| 5 | Student Visa সম্পূর্ণ অনুপস্থিত |
| 6 | Pricing engine সম্পূর্ণ অনুপস্থিত |
| 7 | Public API "key auth" দাবি করলেও middleware attach করাই হয়নি |
| 8 | Lead কখনো Customer-এ convert হতো না |
| 9 | AI মডেলের নাম Laravel table-naming ভুল করাচ্ছিল |
| 10-11 | AI-নির্ভর অংশ যথাযথভাবে blocked চিহ্নিত |
| 12 | সবচেয়ে ব্যাপক লঙ্ঘন: ৪টা সার্ভিসে hardcoded ভুয়া মেট্রিক |
| 13 | কোনো বাগ নেই (greenfield, সঠিকভাবে বানানো) |
| 14 | validation ছিল না; refund-approval gate তৈরি |
| 15 | Brute-force protection অনুপস্থিত; দুটো authorization gate কখনো define হয়নি |

প্যাটার্ন: প্রতিটা ফেজেই handover-ডকুমেন্টের দাবির চেয়ে বাস্তবতা কম ছিল, নিশ্চিত করে যে "89/89 tests passed" দাবি বাস্তবে ঘটেনি।

## 5. Regression check
পুরো controller/route resolution audit, authorize()-এর সব ব্যবহার এখন Gate/Policy দিয়ে কভার।

## 6. Verified / Unverified / Blocked

VERIFIED: সব ফিক্স কোড-রিভিউ করে সঠিক পাওয়া গেছে।
UNVERIFIED: composer audit, rate-limiting বাস্তব আচরণ।
BLOCKED: কিছু নেই।

## 7. Verification

```bash
cd PROJECT
composer install && cp .env.example .env
php artisan key:generate && php artisan jwt:secret
php artisan migrate:fresh
php artisan serve

for i in 1 2 3 4 5 6; do curl -X POST localhost:8000/api/v1/login -d '{"email":"x@x.com","password":"wrong"}' -H "Content-Type: application/json"; done

curl -X POST localhost:8000/admin/optimize-db -H "Authorization: Bearer <ADMIN_TOKEN>"

composer audit
```

## 8. পরবর্তী ফেজ
Phase 16 (SEO + Website Analytics) - পাবলিক ওয়েবসাইট-সম্পর্কিত মেটাডেটা/tracking আর্কিটেকচার।
