# QUDRIX CRM — FRONTEND BATCH 2 REPORT (Booking Detail + Hajj/Umrah)

**তারিখ:** September 20, 2026

## 1. Objective
আগের batch-এ flagged সবচেয়ে business-critical gap মেটানো: P1-এর Mahram/Installment feature-এর কোনো UI ছিল না, আর Hajj/Umrah packages-এর কোনো UI-ই ছিল না।

## 2. যা পাওয়া গেছে (audit)
- `BookingsList.tsx`-এর কোনো row কোথাও link করা ছিল না — কোনো `BookingDetail` page-ই ছিল না, যদিও backend-এর `GET /bookings/{id}` আগে থেকেই `travelers` eager-load করে রিটার্ন করে
- `HajjController@index`/`UmrahController@index` কোনো pagination metadata রিটার্ন করে না (শুধু `{'data': [...]}`, `'pagination'` বা `'meta'` কোনোটাই না) — এটা pre-existing Phase 5 gap, এই ব্যাচে fix করা হয়নি (scope-এর বাইরে), শুধু frontend-এ pagination control বাদ দিয়ে honestly handle করা হয়েছে

## 3. যা বানানো হয়েছে
- **`BookingDetail.tsx`** (নতুন route `/bookings/:id`): traveler তালিকা, প্রতিটা traveler-এর mahram status দেখায় (female traveler-এর জন্য মাহরাম না থাকলে warning), mahram add ফর্ম, verify বাটন; installment plan তৈরি ফর্ম + প্রতিটা plan-এর installment তালিকা + "mark paid" action
- **`BookingsList.tsx`**: booking number এখন `/bookings/:id`-তে link করে (আগে clickable ছিল না)
- **`HajjUmrahPackagesList.tsx`** (নতুন route `/hajj-umrah`): tab দিয়ে Hajj/Umrah টগল, list + create ফর্ম দুটোর জন্যই
- Nav-এ "Hajj / Umrah" আইটেম যোগ (Landmark আইকন)

## 4. Files Changed
নতুন: `pages/bookings/BookingDetail.tsx`, `pages/hajj-umrah/HajjUmrahPackagesList.tsx`
পরিবর্তিত: `pages/bookings/BookingsList.tsx`, `App.tsx`, `layouts/AppLayout.tsx`

## 5-8. Database/API/Security Changes
কোনো backend পরিবর্তন এই ব্যাচে হয়নি — সবকিছু আগে থেকেই বানানো endpoint ব্যবহার করে (`/bookings/{id}`, `/bookings/{id}/mahrams`, `/mahrams`, `/mahrams/{id}/verify`, `/bookings/{id}/installment-plans`, `/installment-plans`, `/installments/{id}/pay`, `/hajj`, `/umrah`) — প্রতিটা `routes/api.php`-তে আসলে registered কিনা আবার cross-check করা হয়েছে।

## 9-11. Tests / Runtime Verification
**UNVERIFIED — npm নেই, তাই আগের batch-এর মতোই TypeScript compile/render কখনো verify করা যায়নি।** Brace/paren balance আর duplicate-route path হাতে/script দিয়ে চেক করা হয়েছে।

## 12. Known Issues
- Room Assignment-এর এখনো কোনো UI নেই (hotel booking-এর জন্য frontend-এ কোনো page-ই নেই, তাই room assignment বসানোর জায়গা নেই — এটা একটা বড়, আলাদাভাবে scope করা দরকার এমন কাজ, তাড়াহুড়ো করে ভুল জায়গায় বসানো হয়নি)
- Hajj/Umrah package list-এ pagination control নেই কারণ backend নিজেই pagination metadata দেয় না (pre-existing, #2 দেখুন)
- `BookingDetail.tsx`-এ mahram-এর জন্য "female traveler" চেক শুধু UI-level heuristic (backend-এ কোনো hard rule নেই যে শুধু female-দের mahram লাগবে) — এটা UX সহায়ক ইঙ্গিত মাত্র, বাধ্যতামূলক নিয়ম না

## 13. Unverified Items
পুরো ব্যাচের TypeScript compile/build/render — env limitation।

## 14. Bugs Fixed
নতুন কোনো bug এই ব্যাচে পাওয়া যায়নি (শুধু UI যোগ)।

## 15. Regression Results
- Brace/paren balance (৫টা নতুন/পরিবর্তিত ফাইল): **PASS**
- Duplicate route path (`App.tsx`): **PASS**
- প্রতিটা call-করা backend endpoint `routes/api.php`-তে সত্যিই registered কিনা: **PASS** (cross-checked)
- ⚪ TypeScript compile: **UNVERIFIED — no npm**

## 16. Deployment Impact
কিছুই না — নতুন dependency লাগেনি।

## 17. Remaining Work
- Room Assignment UI (hotel booking page থেকে শুরু করা দরকার, যেটা এখনো নেইই)
- Student Visa/Al-Azhar UI
- TrackingConfig page (P2)
- Suppliers/Flights/Hotels/Transport UI
- Multi-tenant settings, AI, Automation, Complaints, Reports, Webhooks admin UI
- `HajjController`/`UmrahController`-এর missing pagination metadata (pre-existing backend gap)
- বাস্তব `npm install && tsc -b && npm run build` — এখনো কখনো চালানো হয়নি
