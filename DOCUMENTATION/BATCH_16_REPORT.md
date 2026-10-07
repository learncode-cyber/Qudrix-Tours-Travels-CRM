# QUDRIX CRM — BATCH 16 REPORT (API Key Permissions/Allowed IPs UI)

**তারিখ:** September 24, 2026

## 1. Objective
Batch 10-এ ApiKeysPage বানানোর সময় known-issue হিসেবে রাখা হয়েছিল: backend `permissions`/`allowed_ips` সাপোর্ট করে কিন্তু UI-তে কোনো জায়গা ছিল না। এই ব্যাচে সেটা মেটানো।

## 2. যা করা হয়েছে
- **Create ফর্মে** নতুন দুটো ফিল্ড: Permissions আর Allowed IPs, দুটোই comma-separated text input হিসেবে (JSON array editor-এর চেয়ে সহজ, backend শুধু string array আশা করে)
- **প্রতিটা key-এর পাশে "Edit scope"** action — click করলে inline expand হয়ে permissions/allowed_ips edit করার ফর্ম দেখায়, `PATCH /api-keys/{id}` হিট করে

## 3. একটা React bug নিজে ধরে ঠিক করেছি (ship করার আগেই)
`.map()`-এর ভেতরে প্রতিটা key-row + তার conditional edit-row — দুটো element একসাথে রাখতে shorthand fragment (`<>...</>`) ব্যবহার করেছিলাম প্রথমে। কিন্তু React-এর শর্ট-হ্যান্ড fragment syntax `key` prop নেয় না, আর `.map()`-এর টপ-লেভেল রিটার্নে `key` বাধ্যতামূলক। এটা console warning তো দিতই, list re-render-এ ভুল row নিয়ে ঘুলিয়ে যাওয়ার মতো বাস্তব bug-ও হতে পারত। `Fragment` import করে `<Fragment key={k.id}>` ব্যবহার করে ঠিক করা হয়েছে।

## 4. Files Changed
পরিবর্তিত: `pages/settings/ApiKeysPage.tsx`

## 5-8. Database/API/Security Changes
কিছুই না — existing `PATCH /api-keys/{id}` endpoint ব্যবহার (backend আগে থেকেই `permissions`/`allowed_ips` validate ও সেভ করে, শুধু UI ছিল না)।

## 9-11. Tests / Runtime Verification
**UNVERIFIED — npm নেই,** যথারীতি।

## 12. Known Issues
- Permission string-এর কোনো fixed catalog/autocomplete নেই (backend-এও কোনো enum নেই, free-form array) — ভুল বানান হলে silently কোনো effect নাও থাকতে পারে, backend যেভাবে ব্যবহার করে তার উপর নির্ভর করে
- IP validation client-side নেই, শুধু backend validation-এর উপর নির্ভরশীল

## 13. Unverified Items
পুরো ব্যাচের TypeScript compile/render।

## 14. Bugs Fixed
Fragment/key React bug (#3), ship করার আগেই।

## 15. Regression Results
- Brace/paren balance: **PASS**
- Fragment ট্যাগ ব্যালেন্স: **PASS**
- ⚪ TypeScript compile: **UNVERIFIED**

## 16. Deployment Impact
কিছুই না।

## 17. Remaining Work
BookingDetail refactor, automation action_config dynamic form, AI provider cost fields, aggregate webhook monitoring UI — সব ছোট, অপরিবর্তিত। আর সবচেয়ে গুরুত্বপূর্ণ: বাস্তব `npm`/`PHP` পরিবেশে প্রথমবার চালিয়ে দেখা।
