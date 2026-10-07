# QUDRIX CRM — PHASE P1 REPORT

**তারিখ:** September 18, 2026

## 1. Objective
`MASTER_PROJECT_AUDIT.md`-এর P1 তালিকা বাস্তবায়ন: Hajj/Umrah Mahram tracking, Room Assignment, Installment management, Al-Azhar-নির্দিষ্ট student fields, এবং বাংলা/ইংরেজি/আরবি i18n।

## 2. Planned Features
Mahram relationship tracking · Room-by-room traveler assignment · Installment plans + payment recording · Al-Azhar admission fields/workflow · i18n locale resolution + RTL flag + starter translations

## 3. Implemented Features
- **Mahram:** `MahramRelationship` model + `MahramController` — কো-ট্রাভেলার অথবা non-traveling mahram উভয় ধরনের রেকর্ড, verification workflow (verified_by/verified_at), tenant isolation `booking_travelers`-এর মাধ্যমে (এই টেবিলে নিজস্ব `tenant_id` নেই বলে explicit check জরুরি ছিল)
- **Room Assignment:** `RoomAssignment` model + `RoomAssignmentController` — real `max_occupancy` enforcement (over-capacity assign করলে 422 রিজেক্ট হয়), many-to-many pivot `room_assignment_traveler`
- **Installment:** `InstallmentPlan` + `Installment` model + `InstallmentPlanController` — even split with rounding remainder শেষ installment-এ absorb করা হয় (total ঠিক মিলবে), existing `Payment` মডেল reuse করে (আলাদা parallel ledger বানানো হয়নি), scheduled command `installments:flag-overdue` (daily)
- **Al-Azhar:** `student_visa_applications`-এ নতুন nullable field (`is_al_azhar`, `azhar_faculty`, `azhar_level`, `azhar_registration_number`, `arabic_proficiency_level`) — **নতুন controller/workflow বানানো হয়নি**, existing `StudentVisaController`-এর status-sequence workflow (`inquiry→...→enrolled`) reuse করা হয়েছে, শুধু validation ও fillable আপডেট হয়েছে
- **i18n:** `SetLocale` middleware (priority: header/query → user preference → tenant default → config fallback), `users.locale` column (নতুন), `bn/en/ar` starter `lang/` ফাইল, `X-Text-Direction` response header RTL সাপোর্টের জন্য

**গুরুত্বপূর্ণ self-catch:** প্রথমে `tenants.default_locale` নামে একটা নতুন column বানানোর পরিকল্পনা ছিল, কিন্তু code review-এ ধরা পড়ে `Tenant` মডেলের fillable-এ আগে থেকেই `'language'` আছে (Phase 18 SaaS readiness migration-এ যোগ হয়েছিল) — সেটাই duplicate entity হয়ে যেত। ship করার আগেই সরিয়ে existing `tenants.language` কলাম reuse করা হয়েছে।

## 4. Files Changed
নতুন: `Agent.php`/`Vendor.php` মডেল-কন্ট্রোলার (P0 থেকে বাহিত), `MahramRelationship.php`, `RoomAssignment.php`, `InstallmentPlan.php`, `Installment.php` মডেল; `MahramController.php`, `RoomAssignmentController.php`, `InstallmentPlanController.php`; `app/Http/Middleware/SetLocale.php`; `app/Console/Commands/FlagOverdueInstallments.php`; `lang/{en,bn,ar}/messages.php`
পরিবর্তিত: `BookingTraveler.php`, `HotelBooking.php`, `Booking.php` (নতুন relation), `User.php`, `NotificationTemplate.php`, `StudentVisaApplication.php` (fillable/casts), `StudentVisaController.php` (validation), `routes/api.php`, `routes/console.php`, `bootstrap/app.php` (middleware alias + global api-group append), `config/app.php` (available_locales/rtl_locales)

## 5. Database Changes
- `2024_08_18_000002_...`: `mahram_relationships`, `room_assignments`, `room_assignment_traveler`, `installment_plans`, `installments` টেবিল + `student_visa_applications`-এ Al-Azhar কলাম
- `2024_08_18_000003_...`: `users.locale`, `notification_templates.locale` (+ uniqueness key-তে locale যোগ)

## 6. API Changes
১২টা নতুন route `routes/api.php`-এ (mahrams, room-assignments, installment-plans/installments — সব `jwt.auth+tenant+audit` middleware group-এ, Phase 5-এর existing pattern অনুসরণ করে)

## 7. Frontend Changes
**কিছুই না।** এই sandbox-এ npm/Node নেই, তাই FRONTEND/ ফোল্ডার স্পর্শ করা হয়নি। Backend API আছে, frontend integration বাকি — এটা honestly PARTIAL হিসেবেই রইল, COMPLETE দাবি করা হয়নি।

## 8. Security Changes
- সব নতুন controller-এ tenant isolation ডবল-লেয়ারে: (ক) `TenantMiddleware`-এর automatic global scope (যে টেবিলে `tenant_id` কলাম আছে), (খ) explicit `where('tenant_id', ...)` বা `whereHas` চেইন যেখানে child টেবিলে নিজস্ব `tenant_id` নেই (যেমন `booking_travelers`)
- Room assignment-এ cross-tenant traveler ID injection ঠেকাতে explicit ownership যাচাই

## 9. Tests Run
**কোনো automated test লেখা/চালানো হয়নি এই ব্যাচে।** এটা honestly গ্যাপ — pre-existing test suite pattern (`tests/Api/...`) অনুসরণ করে নতুন feature-এর জন্যও test লেখা উচিত, STATUS.md-তে backlog আইটেম হিসেবে রাখা হলো।

## 10. Test Results
প্রযোজ্য না (উপরের কারণে)।

## 11. Runtime Verification
**UNVERIFIED — Environment limitation।** PHP/MySQL/Node এই sandbox-এ নেই। যা করা হয়েছে তা static/code-level audit শুধু (নিচে #15)।

## 12. Known Issues
- i18n middleware শুধু `routes/api.php` + `api-public.php`-এর customer-facing অংশ + `webhooks-monitoring`-এ কার্যকর (এই তিনটাই `'api'` middleware group name ব্যবহার করে) — `admin/api/api-keys`, `admin/api/webhooks`, `admin/api/integrations`, `webhooks-advanced` — এই route group গুলো `'api'` group নাম ব্যবহার করে না বলে locale resolve হবে না সেখানে (এগুলো admin/internal endpoint, কম গুরুত্বপূর্ণ, কিন্তু honestly উল্লেখ করা দরকার)
- Controller response message এখনো বেশিরভাগ hardcoded English — `lang/` ফাইলগুলো foundation মাত্র, প্রতিটা response-এ `__('messages.key')` বসানো বাকি

## 13. Unverified Items
মাইগ্রেশন conflict-free চলবে কিনা, নতুন route-গুলো আসলে সঠিক response দেয় কিনা, RTL header frontend-এ ঠিকমতো consume হবে কিনা — সবই environment limitation-এর কারণে UNVERIFIED।

## 14. Bugs Fixed
নতুন কোনো pre-existing bug এই ব্যাচে খুঁজে পাওয়া যায়নি (P0-তে যা পাওয়া গিয়েছিল তা আগেই ফিক্স হয়েছে)। একটা **self-caught near-bug**: `tenants.default_locale` নামে duplicate column বানানো থেকে বিরত থাকা হয়েছে (উপরে #3 দেখুন)।

## 15. Regression Results (Full Project, শুধু P1 না)
- Duplicate `Schema::create()` টেবিল নাম: **PASS**
- Dangling `Schema::table()` alter (create ছাড়া): **PASS**
- Route name collision: **PASS**
- নতুন route URI-তে duplicate নেই (existing route-গুলোর সাথে cross-checked): **PASS**
- Brace/paren balance — এই ব্যাচে touched ২৭টা ফাইলের প্রতিটাতে: **PASS**
- Tenant isolation pattern (global scope + child-table explicit check যেখানে দরকার): **PASS** (কোড-রিভিউ ভিত্তিক)
- Migration foreign-key targets সব বিদ্যমান টেবিলের দিকে নির্দেশ করে এবং সঠিক ক্রমে আছে: **PASS**
- ⚪ **DATABASE MIGRATION RUNTIME VERIFICATION: UNVERIFIED**

## 16. Deployment Impact
নতুন migration ৩টা চালাতে হবে প্রোডাকশনে; কোনো breaking change নেই (সব নতুন কলাম nullable বা default সহ, কোনো existing কলাম বাদ দেওয়া হয়নি)।

## 17. Remaining Work
- P2: Meta Pixel/Conversions API/GA4, commission ledger/reconciliation
- i18n coverage সব route group-এ সম্প্রসারণ + controller response string-গুলো `__()`-এ migrate করা
- Frontend integration (এখনো touch করা হয়নি)
- Automated test লেখা এই ব্যাচের feature-গুলোর জন্য
- সবচেয়ে গুরুত্বপূর্ণ, অপরিবর্তিত: real migration + test suite execution বাস্তব PHP/MySQL পরিবেশে
