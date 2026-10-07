# QUDRIX Travel CRM — PHASE 17 রিপোর্ট (পার্ট ২)
## Frontend: Proposals, Invoices, Payments

**তারিখ:** September 2, 2026
**Environment note (অপরিবর্তিত):** network নেই, npm install/build কখনো চালানো যায়নি - UNVERIFIED।

---

## 1. এই ব্যাচের কাজ
পার্ট ১-এ প্রস্তাবিত পরবর্তী ব্যাচ অনুযায়ী - Phase 3-এর sales chain-এর সরাসরি ধারাবাহিকতা:

- Proposals - real list, send/sign/reject action button (Phase 3-এর real state-transition endpoint কল করে, স্ট্যাটাস অনুযায়ী শুধু প্রাসঙ্গিক action দেখায়)।
- Invoices - real list, balance-due হিসাব (backend-এর দেওয়া দুটো real ফিল্ড থেকে হিসাব), void action (শুধু কোনো পেমেন্ট না থাকলে দেখানো হয়)।
- Payments - real list + রেকর্ড-পেমেন্ট ফর্ম, invoice_id query param দিয়ে prefill।

## 2. Response shape যাচাই
প্রতিটা মডিউল বসানোর আগে backend controller-এর index()/eager-load সরাসরি পড়ে নিশ্চিত হওয়া হয়েছে - বিশেষভাবে ProposalController::index()-এ customer/lead সম্পর্ক eager-load হয় কিনা যাচাই করা হয়েছে (হয়)।

## 3. Navigation
সাইডবারে Proposals/Invoices/Payments যোগ করা হয়েছে - শুধু বাস্তবে কাজ করা মডিউলই নেভিগেশনে থাকে।

## 4. Regression check
সম্পূর্ণ import-path যাচাই script আবার চালানো হয়েছে (২০টা ফাইল, সব path রিজলভ করে)।

## 5. Verified / Unverified / Blocked

VERIFIED: সব import path, সব API রেসপন্স-শেপ backend-এর সাথে মিলিয়ে দেখা।
UNVERIFIED: npm install/build কখনো চালানো হয়নি।
BLOCKED: কিছু নেই।

## 6. পরবর্তী ধাপ
বাকি মডিউলগুলোর জন্য অপেক্ষা করছি নির্দেশনার - একই ব্যাচ-ভিত্তিক পদ্ধতিতে চালিয়ে যাব।
