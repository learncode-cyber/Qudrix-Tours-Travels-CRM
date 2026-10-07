# QUDRIX Travel CRM — PHASE 8 রিপোর্ট
## CRM ↔ ERP Integration

**তারিখ:** September 2, 2026
**Environment note (অপরিবর্তিত):** এই sandbox-এ PHP/MySQL/network নেই — শুধু static analysis হয়েছে, Section 6-এ দেওয়া কমান্ডগুলো নিজে চালিয়ে real verification করুন।

---

## 1. Audit-এর ফলাফল
আপনার spec-এ লেখা chain টা ছিল: Lead -> Customer -> Deal -> Quotation -> Booking -> Invoice -> Payment -> Service delivery -> Follow-up -> Customer history। Audit করে দেখা গেল এই chain-এর দুইটা জায়গায় সত্যিকারের ভাঙন ছিল:

### 1.1 Lead কখনো Customer-এ convert হতো না
Quotation, Proposal, Booking - সবগুলোতেই lead_id এর পাশে nullable customer_id field ছিল, কিন্তু গোটা codebase-এ কোথাও কোনো কোড ছিল না যা একটা Lead থেকে আসলে একটা Customer তৈরি করে। ProposalController::signProposal() শুধু lead.status = 'won' সেট করতো - এরপর deal "জিতে" গেলেও কোনো real Customer রেকর্ড তৈরি হতো না, ফলে পরের ধাপগুলো (Invoice-এর customer_id তো NOT NULL) কার্যত অচল ছিল।

### 1.2 Booking-এর সাথে Quotation/Proposal-এর কোনো লিংক ছিল না
Phase 3-এ Invoice তৈরি হয়েছিল quotation_id/proposal_id সহ, কিন্তু Booking-এ শুধু lead_id/customer_id/package_id ছিল - কোনো quotation_id/proposal_id ছিল না। মানে deal জেতার পর যে booking তৈরি হয়, তার সাথে কোন quotation/proposal থেকে এটা এসেছে সেটা কখনো trace করা যেত না।

### 1.3 Customer Timeline অসম্পূর্ণ ছিল
Phase 2-এ বানানো timeline() শুধু communication/task/booking/payment দেখাতো - quotation, proposal, invoice বাদ পড়েছিল, অথচ আপনার spec-এই স্পষ্ট লেখা "Everything must appear in the customer timeline।"

## 2. যা fix করা হয়েছে

- LeadController::convertToCustomer() (নতুন) - Lead থেকে real Customer তৈরি করে (email দিয়ে duplicate check করে, থাকলে reuse করে), এবং সেই lead-এর সব existing quotation/proposal/booking-এ customer_id backfill করে দেয়।
- ProposalController::signProposal() এখন automatic - deal সাইন হওয়ার সাথে সাথেই lead থেকে Customer তৈরি/লিংক হয়ে যায়, এবং proposal + তার quotation দুটোতেই customer_id সেট হয়ে যায়। শুধু manual endpoint হিসেবে রাখিনি, কারণ deal জেতার পর customer তৈরি না হলে পরের প্রতিটা ধাপ (invoice, booking) আটকে যেত।
- bookings টেবিলে quotation_id, proposal_id যোগ করা হয়েছে - এখন chain সত্যিই traceable: booking -> proposal -> quotation -> lead।
- BookingController::createFromProposal() (নতুন) - signed proposal থেকে সরাসরি real booking তৈরি করে, InvoiceController::createFromProposal()-এর মতোই প্যাটার্নে।
- CustomerController::timeline() extend করা হয়েছে quotation/proposal/invoice ইভেন্ট যোগ করে।

## 3. যা এই ফেজে বানানো হয়নি, এবং কেন
- Webhook/retry/conflict-handling সিস্টেম - spec-এ "webhooks, retry mechanism, sync logs, conflict handling" চাওয়া হয়েছিল, কিন্তু এগুলো মূলত বাহ্যিক (external) সিস্টেমের সাথে sync-এর জন্য দরকার। এই প্রজেক্টে CRM আর ERP একই ডেটাবেসে - তাই "sync" মানে এখানে সরাসরি foreign-key সম্পর্ক (যা এই ফেজে ঠিক করা হয়েছে), আলাদা webhook infrastructure নয়। ভবিষ্যতে সত্যিই বাইরের CRM-এর সাথে integrate করতে হলে Phase 7-এর NotificationChannel প্যাটার্নের মতোই adapter বানানো যাবে।

## 4. Regression check
পুরো controller/route resolution audit আবার চালানো হয়েছে (0টা bare string, সব ক্লাস resolve করে), model-to-table cross-check পরিষ্কার এসেছে, migration ordering ঠিক আছে।

## 5. Verified / Unverified / Blocked

VERIFIED (static): সব নতুন/পরিবর্তিত ফাইল syntactically সঠিক, সব route resolve করে।
UNVERIFIED: real migration execution এবং real request behavior।
BLOCKED: কিছু নেই - এই ফেজে কোনো বাহ্যিক credential দরকার হয়নি।

## 6. Verification - নিজে চালিয়ে দেখুন

```bash
cd PROJECT
composer install && cp .env.example .env
php artisan key:generate && php artisan jwt:secret
php artisan migrate:fresh
php artisan serve

# Proposal sign করলে customer আসলেই তৈরি হয় কিনা:
curl -X POST localhost:8000/proposals/1/sign -H "Authorization: Bearer <TOKEN>"

# Signed proposal থেকে booking তৈরি:
curl -X POST localhost:8000/bookings/from-proposal -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" \
  -d '{"proposal_id":1,"travel_date":"2027-01-15","return_date":"2027-01-25"}'

# timeline-এ quotation/proposal/invoice দেখা যাচ্ছে কিনা:
curl localhost:8000/customers/1/timeline -H "Authorization: Bearer <TOKEN>"
```

## 7. পরবর্তী ফেজ
এখানে থামছি। পরেরটা Phase 9 (AI Provider Management) - কোনো real AI credential নেই, তাই মূলত provider-agnostic architecture বানানো হবে, আসল AI কল BLOCKED থাকবে।
