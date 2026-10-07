# QUDRIX CRM — BATCH 8 REPORT (/users endpoint + Complaint Assignment)

**তারিখ:** September 20, 2026

## 1. Objective
আগের ব্যাচে identify করা backlog item মেটানো: staff listing endpoint বানানো, এবং Complaints-এ assignment UI যুক্ত করা।

## 2. যা বানানো হয়েছে (Backend)
- **`UserController@index`** (নতুন) + `GET /users` route: tenant-scoped, শুধু active staff, roles eager-loaded, শুধু `id/name/email/status` ফেরত দেয় (password/mfa_secret এমনিতেই model-level `$hidden`-এ আছে)। ইচ্ছাকৃতভাবে **minimal** — create/update/delete এখানে নেই, কারণ user provisioning আগে থেকেই অন্য জায়গায় (registration/TenantController) আছে, এই ফিক্সের scope শুধু "listing"।
- `ComplaintController@index`-এ `assignedStaff:id,name` eager-load যোগ — আগে শুধু `show()`-এ ছিল, তাই list view-এ কে assigned আছে তা দেখানো যেত না।

## 3. Frontend
`ComplaintsList.tsx`-এ:
- প্রতিটা complaint-এর subtitle-এ "Assigned to [নাম]" (যদি থাকে)
- "Assign to…" dropdown যোগ, যা `/users` থেকে আসা staff তালিকা দেখায় এবং `POST /complaints/{id}/assign` হিট করে

## 4. Files Changed
নতুন: `app/Http/Controllers/UserController.php`
পরিবর্তিত: `routes/api.php` (+`/users`), `app/Http/Controllers/ComplaintController.php` (eager-load), `pages/complaints/ComplaintsList.tsx`

## 5-8. Database/API/Security Changes
কোনো migration না। নতুন route `jwt.auth+tenant+audit` group-এই আছে (existing pattern)। Sensitive field (password, mfa_secret) কখনোই response-এ যায় না (model-level protection, ডবল-নিশ্চিত করা হয়েছে explicit column selection দিয়েও)।

## 9-11. Tests / Runtime Verification
**UNVERIFIED — npm/PHP নেই,** যথারীতি।

## 12. Known Issues
- Lead-এর assignment UI এখনো `/users` ব্যবহার করে না — সেটা পরের ছোট কাজ (এই ব্যাচেই করা যেত, কিন্তু scope-কে ছোট রেখে একটা জিনিস ভালোভাবে শেষ করা হয়েছে)
- `UserController@index`-এ pagination নেই (ইচ্ছাকৃত — dropdown-এর জন্য সব active staff একসাথে দরকার, সাধারণত সংখ্যায় কম থাকে বলে ধরে নেওয়া হয়েছে; বড় organization-এ এটা ভবিষ্যতে সমস্যা হতে পারে)

## 13. Unverified Items
নতুন endpoint সত্যিই সঠিক roles/staff রিটার্ন করে কিনা, assignment UI ঠিকমতো কাজ করে কিনা — env limitation।

## 14. Bugs Fixed
পূর্বে identified MISSING আইটেম (staff-listing endpoint) এখন IMPLEMENTED।

## 15. Regression Results
- Brace/paren balance (৪টা ফাইল): **PASS**
- Duplicate route path (`/users` কোথাও আগে থেকেই আছে কিনা): **PASS**
- ⚪ TypeScript compile / PHP runtime: **UNVERIFIED**

## 16. Deployment Impact
কিছুই না।

## 17. Remaining Work
- Lead assignment UI-তেও `/users` জুড়ে দেওয়া
- Multi-tenant settings, Reports, AI, Automation, Webhooks admin UI — এখনো কোনো UI নেই
- সবচেয়ে গুরুত্বপূর্ণ, অপরিবর্তিত: বাস্তব `npm`/`PHP` পরিবেশে প্রথমবার চালিয়ে দেখা
