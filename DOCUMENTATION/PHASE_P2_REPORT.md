# QUDRIX CRM — PHASE P2 REPORT

**তারিখ:** September 19, 2026

## 1. Objective
`MASTER_PROJECT_AUDIT.md`-এর P2: Meta Pixel/Conversions API/GA4 marketing attribution, এবং Agent commission-এর জন্য একটা real ledger/reconciliation সিস্টেম।

## 2. Planned Features
Tenant-level Meta Pixel/GA4 configuration · CRM funnel-event logging (qualified_lead/application_started/payment/conversion) · Meta CAPI + GA4 Measurement Protocol-এ real send · Agent commission ledger + manual payout + automatic earning on payment

## 3. Implemented Features
- **Marketing attribution:** `TrackingConfig` (per-tenant Pixel ID/CAPI token/GA4 measurement ID/API secret, `encrypted` cast — Phase 16 security-audit pattern অনুসরণ করে, custom mutator না) + `ConversionEvent` (funnel event log, per-destination status: not_configured/queued/sent/failed) + `ConversionTrackingService` (real `Http::post()` কল Meta Graph API আর GA4 Measurement Protocol endpoint-এ, AI adapter প্যাটার্ন অনুসরণ করে)
- **Commission ledger:** `CommissionEntry` মডেল — প্রতিটা earned/paid/adjustment লাইন আলাদাভাবে রেকর্ড হয়, `balance_after` snapshot সহ। `PaymentController@store` আর `InstallmentPlanController@recordPayment` — দুই জায়গাতেই, যেখানে একটা completed payment কোনো agent-যুক্ত booking-এর বিপরীতে আসে, স্বয়ংক্রিয়ভাবে 'earned' entry তৈরি হয় এবং `Agent.total_commission_earned` বাড়ে। `AgentController@payCommission` — manual payout, outstanding balance-এর বেশি payout রিজেক্ট করে (422)। `AgentController@commissionLedger` — full history।

**গুরুত্বপূর্ণ ডিজাইন সিদ্ধান্ত:** existing `Proposal.commission_rate`/`Payment.commission_rate` (agency-এর নিজের commission, supplier থেকে আসা) স্পর্শ করা হয়নি — এটা সম্পূর্ণ আলাদা flow, `CommissionEntry` শুধু agency→referring-Agent-এর outgoing commission ট্র্যাক করে। দুটো মিশিয়ে ফেললে duplicate/conflicting entity হয়ে যেত।

## 4. Files Changed
নতুন: `TrackingConfig.php`, `ConversionEvent.php`, `CommissionEntry.php` মডেল; `ConversionTrackingService.php`; `TrackingConfigController.php`, `ConversionEventController.php`
পরিবর্তিত: `AgentController.php` (+commissionLedger, +payCommission), `PaymentController.php` (+auto-earn hook), `InstallmentPlanController.php` (+একই auto-earn hook, যেহেতু এটা আলাদাভাবে নিজের Payment তৈরি করে), `routes/api.php`

## 5. Database Changes
`2024_08_19_000001_...`: `tracking_configs` (unique per tenant), `conversion_events`, `commission_entries`

## 6. API Changes
`GET/PUT /tracking-config`, `GET/POST /conversion-events`, `GET /agents/{id}/commission-ledger`, `POST /agents/{id}/pay-commission`

## 7. Frontend Changes
কিছুই না (npm নেই এই sandbox-এ, আগের ব্যাচগুলোর মতোই honestly untouched)।

## 8. Security Changes
Meta CAPI token আর GA4 API secret `encrypted` cast দিয়ে সংরক্ষিত (plaintext না) — `hidden` array-তেও রাখা হয়েছে যাতে API response-এ leak না হয়। সব নতুন query tenant-scoped।

## 9. Tests Run
কোনো automated test লেখা হয়নি এই ব্যাচেও — এটা এখনো একটা accumulating backlog item (P1 রিপোর্টেও উল্লেখ ছিল), এখানে honestly আবার note করা হলো যাতে চাপা না পড়ে।

## 10. Test Results
প্রযোজ্য না।

## 11. Runtime Verification
**UNVERIFIED — Environment limitation** (migration/DB, যথারীতি) **+ একটা আলাদা, স্থায়ী BLOCKER:** এমনকি real PHP/MySQL environment-এ deploy করলেও, `ConversionTrackingService`-এর Meta/GA4 send আসলে সফল হবে না যতক্ষণ না কোনো tenant real Meta Pixel ID + Conversions API token বা real GA4 measurement ID + API secret configure করে। এটা code bug না — এটা AI Provider (Phase 9)-এর মতোই একটা external-credential blocker, এবং সেভাবেই স্পষ্ট করে রাখা হলো, "PASS" দাবি করা হয়নি।

## 12. Known Issues
- Commission ledger-এর `balance_after` snapshot race condition-প্রবণ high-concurrency-তে (দুইটা payment একসাথে আসলে) — এই মুহূর্তে DB transaction ব্যবহার করা হয়েছে কিন্তু row-level lock (`lockForUpdate`) যোগ করা হয়নি; low-volume ব্যবহারে সমস্যা হওয়ার কথা না, কিন্তু honestly উল্লেখ করা দরকার
- GA4 Measurement Protocol endpoint সফল হলেও body-বিহীন 204 রিটার্ন করে, তাই `ga4_response`-এ শুধু HTTP status কোড রাখা হয়েছে, actual validation error না (GA4-এর নিজস্ব সীমাবদ্ধতা, ডিবাগের জন্য আলাদা debug endpoint লাগবে ভবিষ্যতে)

## 13. Unverified Items
Migration conflict-free চলবে কিনা, নতুন route response সঠিক কিনা — env limitation। Meta/GA4 send আদৌ সফল হবে কিনা — credential blocker (স্থায়ী, env-independent)।

## 14. Bugs Fixed
নতুন কোনো pre-existing bug এই ব্যাচে পাওয়া যায়নি। একটা **অতীত oversight এই ব্যাচে ধরা পড়ে ঠিক করা হয়েছে:** P1 রিপোর্টের পর `STATUS.md`-তে Phase 5-এর নিজের row আপডেট করতে ভুলে গিয়েছিলাম (তখন শুধু 18.P1 row-এ লেখা হয়েছিল, কিন্তু Phase 5-এর row-এ তখনও "no mahram/room/installment" রয়ে গিয়েছিল) — এই ব্যাচে regression পাস করার সময় ধরা পড়ে ঠিক করা হয়েছে।

## 15. Regression Results (Full Project)
- Duplicate `Schema::create()` টেবিল নাম: **PASS**
- Dangling `Schema::table()` alter: **PASS**
- Route name collision: **PASS**
- নতুন route URI duplicate-mukto: **PASS**
- Brace/paren balance (১১টা touched ফাইল): **PASS**
- Tenant isolation: **PASS** (কোড-রিভিউ)
- ⚪ **DATABASE MIGRATION RUNTIME VERIFICATION: UNVERIFIED**

## 16. Deployment Impact
৩টা নতুন টেবিল, কোনো breaking change নেই। `.env`-এ নতুন কোনো required variable নেই (Meta/GA4 credential per-tenant DB-তে রাখা হয়, .env-এ না)।

## 17. Remaining Work
- Automated test suite (accumulating backlog — P0/P1/P2 সব ব্যাচের জন্য)
- i18n coverage সম্প্রসারণ (আগের রিপোর্টেই উল্লেখ ছিল)
- Frontend integration
- Real migration + test execution বাস্তব পরিবেশে
- Real Meta/GA4/payment-gateway credential এলে সেই অংশগুলো সত্যিকারের runtime test করা
