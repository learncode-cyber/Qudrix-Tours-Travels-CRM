# QUDRIX Travel CRM — PHASE 9 রিপোর্ট
## AI Provider Management

**তারিখ:** September 2, 2026
**Environment note (অপরিবর্তিত):** এই sandbox-এ PHP/MySQL/network নেই।

---

## 1. Audit-এর ফলাফল
Phase 0-এর handover ডকুমেন্টে দাবি করা হয়েছিল "AI Provider Manager skeleton, Encrypted AI credentials" ইতিমধ্যে বানানো হয়ে গেছে। বাস্তবে কোথাও কোনো AI-সম্পর্কিত মডেল, controller, service, বা migration ছিল না - সম্পূর্ণ greenfield।

## 2. যা বানানো হয়েছে

### Provider-agnostic architecture
AIProviderAdapterInterface + তিনটা বাস্তব adapter (GeminiAdapter, OpenAIAdapter, AnthropicAdapter) - প্রতিটা তার provider-এর real, documented API shape অনুসরণ করে লেখা। কোনো invented/fake API contract নেই।

### AIOrchestrator - কেন্দ্রীয় dispatch
এটাই একমাত্র জায়গা যেখান থেকে অ্যাপ্লিকেশন AI provider-কে কল করবে। feature-key অনুযায়ী কোন provider ব্যবহার হবে বের করে, adapter কল করে, ব্যর্থ হলে fallback provider চেষ্টা করে, এবং প্রতিটা attempt - সফল, ব্যর্থ, বা blocked - ai_usage_logs-এ লগ করে।

### Encrypted credentials (সত্যিকারের, invented নয়)
AIProvider::api_key_encrypted Laravel-এর built-in encrypted cast ব্যবহার করে - অ্যাপের real APP_KEY দিয়ে AES-256-CBC এনক্রিপশন। Model-এ $hidden এবং controller-এ explicit column exclusion - API response-এ কখনোই key leak হবে না।

### Usage/cost tracking
প্রতিটা call-এর token count লগ হয়। খরচ হিসাবের জন্য কোনো টাকার অঙ্ক hardcode করিনি - প্রতিটা tenant তাদের নিজস্ব real pricing অনুযায়ী rate সেট করবে; না করলে cost null থাকবে, ভুল অনুমান দেখাবে না।

### Test Connection
testConnection() সত্যিই একটা মিনিমাল completion call করার চেষ্টা করে। এই sandbox-এ network না থাকায় সবসময় ব্যর্থ হবে - এটা honest UNVERIFIED ফলাফল, fake success না।

## 3. একটা bug যা নিজের কোডেই ধরা পড়েছে (regression check-এর সময়)
Model-to-table cross-check ধরেছে: AIProvider, AIFeatureConfig, AIUsageLog - এই তিনটা ক্লাস নামেই consecutive capital letters আছে ("AI")। Laravel-এর Str::snake() প্রতিটা capital letter-এর আগে আলাদা underscore বসায়, তাই "AIProvider" স্বয়ংক্রিয়ভাবে "ai_provider" না হয়ে "a_i_provider" হয়ে যেত - explicit $table override ছাড়া এই তিনটা মডেলের প্রতিটা query ব্যর্থ হতো। তিনটাতেই $table যোগ করে ঠিক করা হয়েছে, এবং পুরো প্রজেক্টে আর কোনো মডেলে একই প্যাটার্ন আছে কিনা আলাদাভাবে স্ক্যান করে দেখা হয়েছে - আর কোনোটা পাওয়া যায়নি।

## 4. যা এই ফেজে বানানো হয়নি, এবং কেন
- বাস্তব AI call verification - adapter কোড real API shape অনুসরণ করে, কিন্তু একটাও এখনো execute হয়নি (network নেই)। real credential বসানোর পরেও এই কোড first-run-untested হিসেবেই গণ্য করা উচিত।
- Tenant-level মাসিক/দৈনিক token budget enforcement - max_tokens per-request limit হিসেবে আছে, কিন্তু "এই মাসে $50-এর বেশি খরচ হলে block করো" জাতীয় enforcement বানানো হয়নি।

## 5. Regression check
পুরো controller/route resolution audit (0 bare string, সব resolve করে), model-to-table cross-check (উপরের bug ধরে ঠিক করার পর পরিষ্কার), migration ordering ঠিক আছে।

## 6. Verified / Unverified / Blocked

VERIFIED (static): সব ফাইল syntactically সঠিক, routes resolve করে, encrypted cast সঠিকভাবে ব্যবহৃত।
UNVERIFIED: তিনটা adapter-ই - network access নেই বলে একটাও বাস্তবে টেস্ট করা যায়নি।
BLOCKED: কোনো tenant-এর real AI credential না থাকলে সব AI feature honestly blocked থাকবে (এটাই সঠিক ডিফল্ট, বাগ না)।

## 7. Verification - নিজে চালিয়ে দেখুন

```bash
cd PROJECT
composer install && cp .env.example .env
php artisan key:generate && php artisan jwt:secret
php artisan migrate:fresh
php artisan serve

# AI provider যোগ করুন:
curl -X POST localhost:8000/ai-providers -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" \
  -d '{"provider":"anthropic","name":"Production Claude","api_key":"<real key>","default_model":"claude-sonnet-4-5","is_default":true}'

# Test connection:
curl -X POST localhost:8000/ai-providers/1/test -H "Authorization: Bearer <TOKEN>"

# Encrypted key কখনো response-এ আসে না, যাচাই করুন:
curl localhost:8000/ai-providers -H "Authorization: Bearer <TOKEN>"
```

## 8. পরবর্তী ফেজ
এখানে থামছি। পরেরটা Phase 10 (AI Sales Agent) - এই ফেজে বানানো AIOrchestrator ব্যবহার করেই বানানো হবে। তবে real কথোপকথনের জন্য একটা কার্যকর tenant-level AI provider লাগবে, যা এখনো কারো কাছে নেই।
