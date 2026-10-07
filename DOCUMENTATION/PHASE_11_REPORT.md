# QUDRIX Travel CRM — PHASE 11 রিপোর্ট
## Sales Strategies + AI Copilot

**তারিখ:** September 2, 2026
**Environment note (অপরিবর্তিত):** এই sandbox-এ PHP/MySQL/network নেই।

---

## 1. Audit-এর ফলাফল
কোনো sales script library, objection library, বা strategy config কোথাও ছিল না - সম্পূর্ণ greenfield। SalesActivity (Phase 0) real ছিল, reuse করা হয়েছে।

## 2. এই ফেজের সবচেয়ে গুরুত্বপূর্ণ সিদ্ধান্ত: কোনটা AI-নির্ভর, কোনটা না
আপনার spec-এর তালিকায় "Deal risk detection" আর "Next-best-action" ছিল। আমি লক্ষ্য করলাম এই দুইটা আসলে AI ছাড়াই বাস্তবে ভালো কাজ করতে পারে - কারণ এগুলোর জন্য দরকার শুধু বাস্তব ডেটা (কতদিন যোগাযোগ হয়নি, quotation/proposal মেয়াদ পার হয়েছে কিনা, follow-up overdue কিনা) থেকে deterministic নিয়ম প্রয়োগ করা, "বুদ্ধিমান" অনুমান নয়। তাই এই দুটোকে সম্পূর্ণ rule-based, deterministic কোড হিসেবে বানানো হয়েছে - AI call ছাড়াই, ফলে network না থাকা সত্ত্বেও এগুলো এই sandbox-এই সত্যিই কাজ করে।

- AICopilotService::dealRiskScore() - real সংকেত: শেষ যোগাযোগের পর কতদিন, follow-up overdue কিনা, quotation/proposal মেয়াদ পার হয়েছে কিনা, stated conversion probability। প্রতিটা risk point-এর একটা explainable কারণ আছে।
- AICopilotService::nextBestAction() - বাস্তব sales-chain অবস্থা (Phase 8-এ ঠিক করা) চেক করে: pending quotation/proposal আছে কিনা, follow-up due/overdue কিনা, প্রথম যোগাযোগ হয়েছে কিনা।

যা সত্যিই AI দরকার করে ("Suggested reply", conversation analysis-এর মতো ওপেন-এন্ডেড কাজ) - সেগুলো honestly AIOrchestrator-নির্ভর রাখা হয়েছে, blocked/unverified হিসেবে চিহ্নিত।

## 3. যা বানানো হয়েছে
- Sales Strategy config - tenant প্রতি একটা default strategy বেছে নেয় (consultative/SPIN/solution/value/relationship/challenger/sandler)।
- Sales Script library + Objection library - tenant-configurable।
- suggestReply() - Phase 10-এর প্যাটার্নেই, tenant-এর real script/objection library এবং configured strategy prompt-এ inject করে।
- Pipeline-wide risk view (/pipeline-risk) - সব active lead-কে risk score অনুযায়ী sort করে দেখায়।

## 4. যা এই ফেজে বানানো হয়নি, এবং কেন
- Conversation analysis / summary - genuinely AI দরকার করে, কোনো deterministic বিকল্প নেই। AIOrchestrator ইতিমধ্যে প্রস্তুত, ভবিষ্যতে সহজেই যোগ করা যাবে।
- Sales coaching (performance-based) - historical won/lost deal-এর প্যাটার্ন বিশ্লেষণ দরকার, একটা আলাদা analytics-ভিত্তিক ফিচার - এই ফেজে scope-এর বাইরে রাখা হয়েছে, ভুয়া সংখ্যা না বানিয়ে।

## 5. Regression check
পুরো controller/route resolution audit (0 bare string, সব resolve করে), model-to-table cross-check পরিষ্কার, migration ordering ঠিক আছে।

## 6. Verified / Unverified / Blocked

VERIFIED (static + logic-level real): dealRiskScore() আর nextBestAction() - এই দুটো আসলেই কাজ করে, AI ছাড়াই।
UNVERIFIED: suggestReply() - network না থাকায় টেস্ট করা যায়নি।
BLOCKED: আগের ফেজগুলোর মতোই AI-নির্ভর অংশ credential ছাড়া blocked।

## 7. Verification - নিজে চালিয়ে দেখুন

```bash
cd PROJECT
composer install && cp .env.example .env
php artisan key:generate && php artisan jwt:secret
php artisan migrate:fresh
php artisan serve

curl localhost:8000/leads/1/deal-risk -H "Authorization: Bearer <TOKEN>"
curl localhost:8000/leads/1/next-best-action -H "Authorization: Bearer <TOKEN>"
curl localhost:8000/pipeline-risk -H "Authorization: Bearer <TOKEN>"

curl -X POST localhost:8000/sales-strategy -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" -d '{"strategy":"spin"}'
```

## 8. পরবর্তী ফেজ
এখানে থামছি। পরেরটা Phase 12 (Analytics + Behavioral Intelligence) - আপনার spec-এই লেখা "No fabricated metrics. If data does not exist, return null" - এটা Phase 6-এর KPI fix-এর সাথে একদম মিলে যায়।
