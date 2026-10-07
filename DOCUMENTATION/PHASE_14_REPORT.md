# QUDRIX Travel CRM — PHASE 14 রিপোর্ট
## Complaint Handling + Automation

**তারিখ:** September 2, 2026
**Environment note (অপরিবর্তিত):** এই sandbox-এ PHP/MySQL/network নেই।

---

## 1. Audit-এর ফলাফল
Complaint মডেল, ComplaintController, ComplaintService - তিনটাই Phase 0 থেকে ছিল, real basic CRUD। কিন্তু SLA tracking, escalation, classification, বা refund-approval gate - কিছুই ছিল না। এবং ComplaintController::updateStatus()-এ কোনো validation ছিল না - $request->status সরাসরি সেভ হতো।

## 2. এই ফেজের সবচেয়ে গুরুত্বপূর্ণ কাজ: "AI কখনো refund প্রতিশ্রুতি দেবে না" - কোডে বাস্তবায়ন
আপনার spec-এর এই নিয়মটা শুধু prompt-এর নির্দেশনায় না রেখে গঠনগতভাবে নিশ্চিত করা হয়েছে:

- একটা complaint-এ compensation-সম্পর্কিত শব্দ থাকলে সেটা involves_compensation=true + approval_status='pending' হয়ে যায় - deterministic keyword detection দিয়ে, AI দিয়ে না।
- approveCompensation()-ই একমাত্র জায়গা যেখান থেকে real Payment (refund) তৈরি হয় - এবং এটা শুধুমাত্র একজন authenticated staff member স্পষ্টভাবে amount দিয়ে কল করলেই ঘটে।
- suggestResponse() (AI-নির্ভর) prompt-এই স্পষ্টভাবে বলে দেয় refund promise না করতে - কিন্তু তার চেয়েও গুরুত্বপূর্ণ, এমনকি AI নির্দেশনা উপেক্ষা করলেও, তার response থেকে সরাসরি কোনো Payment তৈরি হওয়ার কোনো কোড-পথই নেই।

## 3. অন্যান্য যা ঠিক/বানানো হয়েছে
- updateStatus() validation bug ফিক্স - এখন নির্দিষ্ট enum দিয়ে সীমাবদ্ধ।
- Rule-based classification - keyword matching দিয়ে category ও priority নির্ধারণ - deterministic, AI ছাড়াই কাজ করে।
- SLA tracking - priority-ভিত্তিক deadline + breach detection sweep।
- Human handoff/assignment - assign() endpoint, real।
- Resolution gate - resolveComplaint() এখন compensation approve না হওয়া পর্যন্ত resolve হতে বাধা দেয়।

## 4. যা এই ফেজে বানানো হয়নি, এবং কেন
- সত্যিকারের AI classification/sentiment - keyword-based rule বাস্তবে কাজ করে কিন্তু genuinely ভাষাগত বোঝাপড়ার বিকল্প না। AI-কে শুধু "suggested response"-এ সীমাবদ্ধ রাখা হয়েছে।
- Complaint history-কে Customer Timeline-এ যোগ করা - সময়ের কারণে এই ফেজে করা হয়নি, ভবিষ্যৎ addition হিসেবে নোট করে রাখছি।

## 5. Regression check
পুরো fake-metric sweep - শুধু SLA ঘণ্টা-সংখ্যা পাওয়া গেছে, যা একটা ব্যবসায়িক নিয়ম। controller/route resolution, route-ordering যাচাই, model-to-table cross-check - সব পরিষ্কার।

## 6. Verified / Unverified / Blocked

VERIFIED: classification, SLA calculation, escalation sweep, compensation-approval gate - সবই deterministic।
UNVERIFIED: suggestResponse() - AI call, network না থাকায় টেস্ট করা যায়নি।
BLOCKED: কিছু নেই নতুন।

## 7. Verification - নিজে চালিয়ে দেখুন

```bash
cd PROJECT
composer install && cp .env.example .env
php artisan key:generate && php artisan jwt:secret
php artisan migrate:fresh
php artisan serve

curl -X POST localhost:8000/complaints -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" \
  -d '{"booking_id":1,"customer_id":1,"title":"Refund needed","description":"I want a refund, the hotel was terrible and staff was rude"}'

curl -X POST localhost:8000/complaints/1/resolve -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" -d '{"resolution":"done"}'

curl -X POST localhost:8000/complaints/1/approve-compensation -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" -d '{"amount":100}'
```

## 8. পরবর্তী ফেজ
এখানে থামছি। পরেরটা Phase 15 (Security + Audit + Hardening) - এটা একটা পূর্ণাঙ্গ security review হবে।
