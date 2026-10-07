# QUDRIX CRM — FRONTEND BATCH 7 REPORT (Complaints)

**তারিখ:** September 20, 2026

## 1. Objective
Phase 14 (Complaint Handling)-এর কোনো UI ছিল না — এই ব্যাচে সেটা মেটানো।

## 2. একটা real backend gap ধরা পড়েছে (ফিক্স করা হয়নি, স্পষ্ট করে জানানো হলো)
`ComplaintController@assign`-এর জন্য একজন `user_id` লাগে (কোন staff member-কে assign করা হচ্ছে), কিন্তু পুরো `routes/api.php`-তে **কোথাও কোনো `/users` listing endpoint নেই** — তাই frontend-এর কোনো উপায় নেই কোন user আছে তা জানার, assignment dropdown বানানোর মতো ন্যূনতম তথ্যও নেই। এটা শুধু Complaints-এর সমস্যা না — Lead-এর `assigned_to`-র জন্যও একই সমস্যা প্রযোজ্য (কোথাও `/leads/{id}/assign`-এর জন্য staff dropdown বানানো সম্ভব না)।

**এই ব্যাচে যা করা হয়েছে:** "assign to user" ফিচার UI-তে বাদ দেওয়া হয়েছে (ভুলভাবে হার্ডকোড করা user_id দিয়ে ভাঙা UI বানানোর চেয়ে ভালো), এবং STATUS.md-তে MISSING হিসেবে স্পষ্ট নথিভুক্ত করা হয়েছে যাতে ভবিষ্যতে P-priority backlog-এ এটা ধরা পড়ে। ফিক্স করিনি কারণ এটা নতুন একটা `UserController`+route+policy দরকার — এই ব্যাচের scope-এর বাইরে একটা আলাদা কাজ।

## 3. যা বানানো হয়েছে
`ComplaintsList.tsx` (`/complaints`):
- Status অনুযায়ী filter (open/in_progress/escalated/resolved/closed/all)
- Create ফর্ম: customer + booking dropdown (existing `/customers`, `/bookings` থেকে), title, description — category/priority backend স্বয়ংক্রিয়ভাবে classify করে তাই সেটা UI-তে input হিসেবে নেই (একটা note দিয়ে জানানো হয়েছে)
- প্রতিটা complaint-এ status change dropdown, resolution note লিখে "Resolve" বাটন, compensation লাগলে "Approve compensation" (browser `prompt()` দিয়ে amount নেয় — এই ব্যাচে সময়ের কারণে একটা full modal বানানো হয়নি, কিন্তু functionally কাজ করে)
- সব backend error (invalid status transition ইত্যাদি) সরাসরি দেখায়

## 4. Files Changed
নতুন: `pages/complaints/ComplaintsList.tsx`
পরিবর্তিত: `App.tsx`, `layouts/AppLayout.tsx`

## 5-8. Database/API/Security Changes
কিছুই না — existing endpoint ব্যবহার। কোনো নতুন backend কোড লেখা হয়নি এই ব্যাচে।

## 9-11. Tests / Runtime Verification
**UNVERIFIED — npm নেই।** যথারীতি brace/paren balance + endpoint-existence cross-check।

## 12. Known Issues
- **Staff assignment feature নেই** (#2, সবচেয়ে গুরুত্বপূর্ণ finding এই ব্যাচের)
- Compensation approval-এর জন্য browser `prompt()` ব্যবহার করা হয়েছে, একটা proper modal না — কাজ করে কিন্তু UX হিসেবে placeholder-মানের
- ComplaintController@index-ও pagination metadata দেয় না (Hajj/Flight/Transport-এর মতোই pre-existing gap)

## 13. Unverified Items
পুরো ব্যাচের TypeScript compile/render।

## 14. Bugs Fixed
কোনো bug ফিক্স হয়নি এই ব্যাচে — শুধু একটা নতুন gap (#2) identify এবং honestly scope-এর বাইরে রাখা হয়েছে।

## 15. Regression Results
- Brace/paren balance (৩টা ফাইল): **PASS**
- Duplicate route path: **PASS**
- Called backend endpoint existence: **PASS**
- ⚪ TypeScript compile: **UNVERIFIED**

## 16. Deployment Impact
কিছুই না।

## 17. Remaining Work
- **P-priority backlog item (নতুন):** `UserController` + `/users` listing endpoint (tenant-scoped, active staff) — Complaints আর Leads উভয়ের assignment UI-র জন্যই দরকার
- Compensation approval-এর জন্য proper modal (prompt() replace করা)
- Multi-tenant settings, Reports, AI, Automation, Webhooks admin UI — এখনো কোনো UI নেই
- সবচেয়ে গুরুত্বপূর্ণ, অপরিবর্তিত: বাস্তব `npm`/`PHP` পরিবেশে প্রথমবার চালিয়ে দেখা
