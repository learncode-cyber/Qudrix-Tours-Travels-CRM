# QUDRIX CRM — FRONTEND BATCH 4 REPORT (Student Visa/Al-Azhar + Tracking Settings)

**তারিখ:** September 20, 2026

## 1. Objective
বাকি থাকা দুটো ছোট কিন্তু গুরুত্বপূর্ণ gap মেটানো: Student Visa/Al-Azhar-এর কোনো UI ছিল না (Phase 5 backend থেকেই), আর P2-এর TrackingConfig-এর কোনো settings page ছিল না।

## 2. যা বানানো হয়েছে
- **`StudentVisaList.tsx`** (নতুন `/student-visas`): list + create ফর্ম, যেখানে "Al-Azhar admission" checkbox টিক দিলে Azhar faculty/level field দেখায় (নাহলে university field) — backend-এর `required_if:is_al_azhar,true` validation-এর সাথে UI-level সামঞ্জস্য রাখা হয়েছে। প্রতিটা application-এর পাশে "Advance to [পরবর্তী status]" বাটন, যা backend-এর sequential status-machine (`advanceStatus`) সরাসরি হিট করে — এক ধাপ বাদ দিয়ে এগোনোর চেষ্টা করলে backend যে 400 error দেয় সেটা UI-তে দেখানো হয়, silently ignore করা হয় না
- **`TrackingConfigPage.tsx`** (নতুন `/settings/tracking`): Meta Pixel/CAPI token আর GA4 measurement ID/secret সেভ করার ফর্ম, "configured/not configured" ব্যাজ

## 3. একটা সম্ভাব্য bug নিজে ধরে ফিক্স করেছি (ship করার আগে)
`TrackingConfigPage`-এ password-type field-এ placeholder ছিল "leave blank to keep current" — কিন্তু প্রথম ড্রাফটে ফর্ম submit করলে খালি string-ই backend-এ পাঠানো হতো, যা `TrackingConfig::updateOrCreate`-এ গিয়ে existing encrypted token/secret **overwrite করে ফেলত** (ব্যবহারকারীর উদ্দেশ্যের ঠিক উল্টো)। Submit করার আগেই ধরা পড়ে ফিক্স করা হয়েছে — এখন token/secret field খালি থাকলে সেগুলো payload-এই পাঠানো হয় না।

## 4. Files Changed
নতুন: `pages/student-visa/StudentVisaList.tsx`, `pages/settings/TrackingConfigPage.tsx`
পরিবর্তিত: `App.tsx`, `layouts/AppLayout.tsx`

## 5-8. Database/API/Security Changes
কিছুই না — শুধু existing endpoint ব্যবহার (`/student-visas`, `/student-visas/{id}/advance`, `/tracking-config`)।

## 9-11. Tests / Runtime Verification
**UNVERIFIED — npm নেই,** যথারীতি। brace/paren balance আর প্রতিটা called endpoint `routes/api.php`-তে registered কিনা — দুটোই cross-check করা হয়েছে।

## 12. Known Issues
- `StudentVisaList`-এ pagination control নেই যদিও backend `pagination` key ঠিকই দেয় (এই controller-এ convention সঠিক) — শুধু frontend-এ pagination UI যোগ করা হয়নি এই ব্যাচে, স্কোপ ছোট রাখতে
- TrackingConfig-এর "Advance" বাটনের মতো কোনো real-time validation নেই যে pixel ID format সঠিক কিনা — backend-ই একমাত্র সত্যিকারের validation

## 13. Unverified Items
পুরো ব্যাচের TypeScript compile/render — env limitation।

## 14. Bugs Fixed
নিজের লেখা TrackingConfigPage-এর blank-secret-overwrite সমস্যা (#3), ship করার আগেই।

## 15. Regression Results
- Brace/paren balance (৪টা নতুন/পরিবর্তিত ফাইল): **PASS**
- Duplicate route path: **PASS**
- Called backend endpoint existence: **PASS**
- ⚪ TypeScript compile: **UNVERIFIED**

## 16. Deployment Impact
কিছুই না।

## 17. Remaining Work
Suppliers/Flights/Transport UI, multi-tenant settings, AI, Automation, Complaints, Reports, Webhooks admin UI — এখনো কোনো UI নেই। আর সবসময়ের মতো, সবচেয়ে গুরুত্বপূর্ণ বাকি কাজ: বাস্তব `npm`/`PHP` পরিবেশে গিয়ে এই এতগুলো ব্যাচের কোনো একটাই প্রথমবার সত্যিই চালিয়ে দেখা।
