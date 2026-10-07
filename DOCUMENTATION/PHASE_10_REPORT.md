# QUDRIX Travel CRM — PHASE 10 রিপোর্ট
## AI Sales Agent

**তারিখ:** September 2, 2026
**Environment note (অপরিবর্তিত):** এই sandbox-এ PHP/MySQL/network নেই।

---

## 1. Audit-এর ফলাফল
কোথাও কোনো conversation/message storage ছিল না। LeadScore (Phase 0) real, কাজ করা lead-scoring লজিক ছিল - এটা reuse করা হয়েছে, নতুন করে বানানো হয়নি।

## 2. যা বানানো হয়েছে

### Conversation storage (fully real)
Conversation + ConversationMessage - প্রতিটা মেসেজ (customer/agent/system/staff) স্টোর হয়, conversation-এর status (active/needs_human/escalated/closed) ট্র্যাক হয়। এটা AI ছাড়াই সম্পূর্ণ কাজ করে।

### দুইটা কড়া নিয়ম, প্রম্পট-এর কথায় নয়, কোডে বাস্তবায়ন করা হয়েছে
আপনার spec-এ দুটো স্পষ্ট নিয়ম ছিল, দুটোই শুধু prompt-এ লিখে না রেখে গঠনগতভাবে নিশ্চিত করা হয়েছে:

1. "AI must NEVER invent availability... or external pricing" - AISalesAgentService::buildPrompt() তেনন্টের real, active Package রেকর্ড (Phase 6-এর pricing engine যেই একই ডেটা ব্যবহার করে) সরাসরি system prompt-এ বসিয়ে দেয়, এটাই AI-কে দেওয়া একমাত্র প্যাকেজ/দামের তথ্যের উৎস। AI-কে যা কখনো বলা হয়নি, সেটা সে উদ্ভাবন করতে পারবে না।
2. Human handoff নির্ভরযোগ্য হতে হবে, AI-এর সিদ্ধান্তের উপর নির্ভরশীল না - একটা deterministic keyword-trigger (customer-এর মেসেজে "talk to a human" জাতীয় phrase) AI call করার আগেই চেক হয়। মানে AI provider সম্পূর্ণ ব্যর্থ/blocked হলেও, "human agent" চাইলে সেটা সবসময় কাজ করবে। AI call ব্যর্থ হলেও সার্ভিস honestly fallback করে human handoff-এ যায়, fake/broken reply না দিয়ে।

### AIOrchestrator পুনরায় ব্যবহার
Phase 9-এ বানানো central dispatch-ই এখানে ব্যবহৃত হয়েছে - নতুন কোনো প্যারালাল AI-calling কোড লেখা হয়নি।

### CRM chain-এর সাথে যুক্ত
নতুন conversation শুরু হলে email দেওয়া থাকলে real Lead তৈরি/খোঁজা হয় (source: 'ai_chat') - Phase 8-এ ঠিক করা chain অনুসরণ করে।

## 3. যা এই ফেজে বানানো হয়নি, এবং কেন

- প্রকৃত conversational প্রতিক্রিয়া - network না থাকায় AI call কখনো সফল হবে না। প্রতিটা sendMessage() call honestly needs_human-এ escalate করবে। এটাই সঠিক আচরণ - fake AI reply বানিয়ে দেখানো হয়নি।
- SPIN-selling/objection-handling-এর জন্য আলাদা কোডেড লজিক - এগুলো prompt-এর নির্দেশনায় লেখা আছে, কিন্তু এটা কতটা ভালো কাজ করে তা model-এর সক্ষমতার উপর নির্ভর করে, যা কখনো টেস্ট করা যায়নি।
- Sentiment/buying-intent detection - Conversation::buying_intent কলাম আছে, কিন্তু স্বয়ংক্রিয়ভাবে পূরণ করার কোড এই ফেজে যোগ করা হয়নি।
- Function calling / tool use - Phase 9-এর adapter গুলো এখনো শুধু raw text completion সাপোর্ট করে, structured function-calling নয়।

## 4. Regression check
পুরো controller/route resolution audit (0 bare string, সব resolve করে), model-to-table cross-check পরিষ্কার, migration ordering ঠিক আছে।

## 5. Verified / Unverified / Blocked

VERIFIED (static): conversation storage logic, keyword-based handoff trigger, prompt-building logic।
UNVERIFIED: AIOrchestrator-এর মাধ্যমে প্রকৃত AI প্রতিক্রিয়া - network access না থাকায় কখনো টেস্ট করা যায়নি।
BLOCKED: real conversational সহায়তা - কোনো working AI credential না থাকলে।

## 6. Verification - নিজে চালিয়ে দেখুন

```bash
cd PROJECT
composer install && cp .env.example .env
php artisan key:generate && php artisan jwt:secret
php artisan migrate:fresh
php artisan serve

curl -X POST localhost:8000/conversations -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" \
  -d '{"name":"Jane Doe","email":"jane@example.com","channel":"web_chat"}'

curl -X POST localhost:8000/conversations/1/messages -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" \
  -d '{"message":"I want a trip to Egypt for 4 people"}'

curl -X POST localhost:8000/conversations/1/messages -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" \
  -d '{"message":"I want to talk to a human"}'
```

## 7. পরবর্তী ফেজ
এখানে থামছি। পরেরটা Phase 11 (Sales Strategies + AI Copilot) - এই ফেজের AISalesAgentService/AIOrchestrator-এর উপর ভিত্তি করেই বানানো হবে।
