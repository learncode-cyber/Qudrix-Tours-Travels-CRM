# QUDRIX CRM — BATCH 14 REPORT (Webhook Admin UI)

**তারিখ:** September 22, 2026

## 1. Objective
আগের ব্যাচে (Batch 13) backend-এর critical IDOR ফিক্স করার পর, এখন সেই নিরাপদ backend-এর উপর Webhook admin UI বানানো।

## 2. আরেকটা real bug ধরা পড়েছে UI বানাতে গিয়ে — API routing mismatch
`admin/api/webhooks` route-গুলো `routes/api-public.php`-তে, যেটা `bootstrap/app.php`-এর `then` callback দিয়ে লোড হয় — **ইচ্ছাকৃতভাবে কোনো automatic `/api` prefix ছাড়া** (এই সেশনেরই প্রথম দিকের একটা ফিক্স, ঠিক এই double-prefix সমস্যা এড়ানোর জন্যই করা হয়েছিল)। কিন্তু frontend-এর `api.ts`-এর `API_BASE` আগে থেকেই `/api` দিয়ে শেষ হয়। মানে `api.get('/admin/api/webhooks')` করলে ফলাফল হতো `.../api/admin/api/webhooks` — একটা ভুল, ডবল `/api` URL, যা কখনো match করত না বাস্তব route-এর সাথে।

**ফিক্স:** `api.ts`-এ নতুন `ADMIN_BASE`/`adminApi` client যোগ করা হয়েছে (existing `AUTH_BASE`/`authApi`-এর মতোই প্যাটার্নে, একই auth-header/401-handling interceptor সহ) — যেটা `/api` ছাড়া root URL ব্যবহার করে, বিশেষভাবে `admin/api/*` route-গুলোর জন্য।

## 3. যা বানানো হয়েছে
- **`WebhooksList.tsx`** (`/webhooks`): তালিকা, নতুন webhook তৈরি (API key select + event checkbox — backend-এর `getAvailableEvents()` থেকে dynamically আসা event তালিকা)
- **`WebhookDetail.tsx`**: delivery statistics, Send test event, Activate/Deactivate, Rotate secret (নতুন secret একবারই দেখায়, ঠিক API Key-এর মতোই secret-once প্যাটার্ন পুনরায় প্রয়োগ করা হয়েছে), delivery তালিকা + failed delivery-তে Retry বাটন

## 4. Files Changed
নতুন: `pages/webhooks/WebhooksList.tsx`, `pages/webhooks/WebhookDetail.tsx`
পরিবর্তিত: `lib/api.ts` (+ADMIN_BASE/adminApi), `App.tsx`, `layouts/AppLayout.tsx`

## 5-8. Database/API/Security Changes
কোনো backend কোড পরিবর্তন হয়নি এই ব্যাচে (আগের ব্যাচেই security fix হয়ে গেছে) — শুধু frontend routing ফিক্স (#2)।

## 9-11. Tests / Runtime Verification
**UNVERIFIED — npm/PHP নেই,** যথারীতি। এই ব্যাচে বিশেষভাবে গুরুত্বপূর্ণ: `adminApi` client আসলে সঠিক URL বানায় কিনা তা browser network tab ছাড়া নিশ্চিত করার কোনো উপায় নেই এখানে — path string মিলিয়ে যুক্তি দিয়ে যাচাই করা হয়েছে, বাস্তব HTTP call করে না।

## 12. Known Issues
- Aggregate/system-wide webhook monitoring endpoint (`monitorAllWebhooks`, `getSystemHealth` ইত্যাদি — Batch 13-এর report-এ যেগুলো ইচ্ছাকৃতভাবে touch করা হয়নি) — এখনো কোনো UI নেই, এবং সেগুলোর cross-tenant aggregation নিয়ে প্রশ্নও এখনো খোলা
- Webhook payload/secret দেখানোর কোনো "view payload" UI নেই, শুধু status/response code

## 13. Unverified Items
পুরো ব্যাচের TypeScript compile/render, এবং `adminApi`-এর URL construction বাস্তবে সঠিক কিনা।

## 14. Bugs Fixed
API routing double-prefix mismatch (#2)।

## 15. Regression Results
- Brace/paren balance (৫টা ফাইল): **PASS**
- Duplicate route path: **PASS**
- Called backend endpoint existence (প্রতিটা admin/api/webhooks/* path রুট ফাইলে line-by-line মিলিয়ে): **PASS**
- ⚪ TypeScript compile / বাস্তব HTTP call: **UNVERIFIED**

## 16. Deployment Impact
কিছুই না।

## 17. Remaining Work
এখন প্রতিটা বড় backend feature area-রই অন্তত কিছু UI আছে। বাকি যা আছে সব ছোট, নির্দিষ্ট item (আগের রিপোর্টগুলোতে তালিকাভুক্ত):
- BookingDetail.tsx refactor (maintainability)
- Automation action_config dynamic form + step reordering
- API key permissions/allowed_ips UI
- AI provider cost fields + per-feature usage breakdown
- Compensation-approval modal (এখনো browser prompt())
- Aggregate webhook monitoring UI (#12)
- **সবচেয়ে গুরুত্বপূর্ণ, ১৪টা ব্যাচ ধরে অপরিবর্তিত:** এর একটাও এখনো বাস্তব `npm`/`PHP` পরিবেশে চালিয়ে দেখা হয়নি
