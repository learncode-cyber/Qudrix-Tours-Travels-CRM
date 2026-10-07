# QUDRIX Travel CRM — P0 Remediation Batch 1

**তারিখ:** September 18, 2026 — `MASTER_PROJECT_AUDIT.md`-এর P0 তালিকা থেকে প্রথম ব্যাচ। এটাকে আলাদা "Phase 19" নাম দেওয়া হয়নি, কারণ Phase 19 আগে থেকেই Billing/Subscription-এর জন্য reserved।

## যা করা হয়েছে

1. **Agent/Vendor আলাদা করা হয়েছে Supplier থেকে** (`agents`, `vendors` টেবিল, migration `2024_08_18_000001`)
   - `Agent`: referral/sub-agent, `agent_code`, commission_type (percentage/fixed), commission_rate, earned/paid ledger fields, `leads`/`bookings` relation
   - `Vendor`: non-travel operational vendor (printing/marketing/software/office_supplies/utilities/other)
   - `leads` ও `bookings` টেবিলে nullable `agent_id` foreign key যোগ হয়েছে যাতে referral attribution ট্র্যাক করা যায়
   - `AgentController`, `VendorController` — full CRUD + `GET /agents/{id}/performance` (agent-এর leads/bookings count + commission balance)
   - Route যোগ হয়েছে `routes/api.php`-এ, Supplier-এর ঠিক পাশেই, একই `jwt.auth+tenant+audit` middleware group-এ

2. **Supplier-এর নিজের একটা bug ধরা পড়েছে এবং ফিক্স হয়েছে:** `routes/api.php`-এ `Route::apiResource('suppliers', ...)` পুরো CRUD route রেজিস্টার করত, কিন্তু `SupplierController`-এ `update`/`destroy` মেথডই ছিল না — মানে কেউ supplier edit/delete করতে গেলে runtime-এ 500 error পেত। দুটো মেথডই এখন যোগ করা হয়েছে, বাকি মেথডগুলোর সাথে সামঞ্জস্যপূর্ণভাবে।

## Verification

- Duplicate table/alter-table check: পাস
- Route name collision: পাস
- Brace/paren balance (সব নতুন ও edited ফাইলে): পাস
- ⚪ **UNVERIFIED — Environment limitation:** এই migration সত্যিই কনফ্লিক্ট ছাড়া চলবে কিনা (foreign key add করার সময় `leads`/`bookings`-এ existing data থাকলে সমস্যা হবে কিনা), এবং নতুন controller/route আসলে HTTP call-এ সঠিক response দেয় কিনা — এই sandbox-এ PHP/MySQL না থাকায় সরাসরি রান করে দেখা যায়নি।

## এখনো বাকি (P0 তালিকার বাকি অংশ ও তার পরের)

- P0: real migration + test execution (এখনো সবচেয়ে গুরুত্বপূর্ণ, environment ছাড়া সম্ভব না)
- P1: Hajj/Umrah Mahram + Room Assignment + Installment
- P1: i18n architecture
- P1: Al-Azhar-নির্দিষ্ট fields
- P2: Meta Pixel/Conversions API/GA4
- P2: Commission ledger/reconciliation (Agent-এর জন্য basic ledger field আজ যোগ হয়েছে, কিন্তু পূর্ণাঙ্গ reconciliation system এখনো বাকি)

পরবর্তী reply-তে P1 আইটেমগুলো (বিশেষত Hajj/Umrah Mahram+Room+Installment) নিয়ে এগোব।
