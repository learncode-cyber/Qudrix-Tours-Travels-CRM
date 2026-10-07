# QUDRIX CRM — BATCH 10 REPORT (Organization Settings + API Keys)

**তারিখ:** September 20, 2026

## 1. Objective
Phase 18 (Multi-tenant SaaS Readiness)-এর UI zero ছিল — tenant নিজের profile/branding/plan দেখতে বা API key নিয়ন্ত্রণ করার কোনো জায়গা ছিল না। এই ব্যাচে সেটা মেটানো।

## 2. যা বানানো হয়েছে
- **`TenantSettingsPage.tsx`** (`/settings/organization`): tenant profile ফর্ম (name/description/timezone/currency/language/logo/primary color), বর্তমান subscription plan দেখায়, available plan তালিকা
- **`ApiKeysPage.tsx`** (`/settings/api-keys`): key তালিকা (masked key, usage count, last used, active/revoked status), নতুন key তৈরি, revoke action

## 3. একটা গুরুত্বপূর্ণ UX detail সাবধানে হ্যান্ডেল করা হয়েছে
`ApiKeyController@store`-এর response-এ `secret` ফিল্ড **শুধু তৈরির সময়ই একবার** থাকে — `index()`-এ আর কখনো ফেরত আসে না (backend সঠিকভাবেই এটা লুকিয়ে রাখে)। তাই frontend-এ creation-এর ঠিক পরপর secret-টা একটা highlighted card-এ দেখানো হয় ("save this now — it will not be shown again") এবং local state-এ রাখা হয়, কোনো re-fetch থেকে না — কারণ re-fetch করলে সেটা আর কখনো পাওয়া যাবে না।

## 4. Files Changed
নতুন: `pages/settings/TenantSettingsPage.tsx`, `pages/settings/ApiKeysPage.tsx`
পরিবর্তিত: `App.tsx`, `layouts/AppLayout.tsx`

## 5-8. Database/API/Security Changes
কিছুই না — সব existing endpoint (`/tenant`, `/subscription-plans`, `/api-keys/*` — P0-তেই route করা হয়েছিল)।

## 9-11. Tests / Runtime Verification
**UNVERIFIED — npm/PHP নেই,** যথারীতি।

## 12. Known Issues
- `primary_color` field-এ HTML `<input type="color">` ব্যবহার করা হয়েছে — কাজ করার কথা, কিন্তু browser রেন্ডারিং verify করা যায়নি
- API key-এর `permissions`/`allowed_ips` ফিল্ড (backend সাপোর্ট করে) UI-তে নেই এখনো — শুধু name/description রাখা হয়েছে scope ছোট রাখতে

## 13. Unverified Items
পুরো ব্যাচের TypeScript compile/render।

## 14. Bugs Fixed
কোনো bug ফিক্স হয়নি — শুধু নতুন UI।

## 15. Regression Results
- Brace/paren balance (৪টা ফাইল): **PASS**
- Duplicate route path: **PASS**
- Called backend endpoint existence: **PASS**
- ⚪ TypeScript compile: **UNVERIFIED**

## 16. Deployment Impact
কিছুই না।

## 17. Remaining Work
- API key-এর permissions/allowed_ips UI
- AI, Automation, Webhooks admin UI — এখনো কোনো UI নেই (এখন সবচেয়ে বড় বাকি অংশ)
- সবচেয়ে গুরুত্বপূর্ণ, অপরিবর্তিত: বাস্তব `npm`/`PHP` পরিবেশে প্রথমবার চালিয়ে দেখা
