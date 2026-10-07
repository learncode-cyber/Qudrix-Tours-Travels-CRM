# QUDRIX CRM — BATCH 12 REPORT (Automation UI + Security Fix)

**তারিখ:** September 21, 2026

## 1. Objective
Phase 14 (Automation)-এর UI বানানো। কাজ শুরু করতেই দুটো গুরুত্বপূর্ণ জিনিস ধরা পড়েছে — একটা security bug, একটা functional gap।

## 2. Security fix: cross-tenant mass-assignment (P0-মানের)
`AutomationController@update` এবং `DashboardController@update` — দুটোই সরাসরি `$model->update($request->all())` করত। `Automation`-এর `$fillable`-এ `tenant_id` আছে, `Dashboard`-এর `$fillable`-এ `tenant_id` **এবং** `user_id` দুটোই আছে। মানে কোনো authenticated user নিজের তৈরি একটা automation/dashboard-এর id দিয়ে PUT request পাঠিয়ে body-তে `{"tenant_id": <অন্য tenant-এর id>}` পাঠালে সেই রেকর্ডটা **অন্য tenant-এর নামে reassign** করে দিতে পারত — একটা প্রকৃত cross-tenant data-integrity/isolation violation। `HajjController`-এ একই ধরনের একটা bug আগেই (Phase 5 audit-এ) ফিক্স হয়ে গিয়েছিল বলে কমেন্টে পাওয়া গেছে, কিন্তু এই দুটো তখন miss হয়ে গিয়েছিল।

**ফিক্স:** দুটো controller-এই `$request->all()`-এর বদলে explicit `$request->validate([...])` বসানো হয়েছে, `tenant_id`/`user_id` allowed field list থেকে বাদ দিয়ে।

**পুরো প্রজেক্টে একই প্যাটার্নের আর কোনো instance আছে কিনা খুঁজে দেখা হয়েছে** — `grep`-এ আর কিছু পাওয়া যায়নি (শুধু HajjController-এর comment, যেটা আগেই ঠিক হয়ে গেছে)।

## 3. Functional gap: automation step তৈরির কোনো route-ই ছিল না
`Automation` তৈরি, execute, test — সবকিছুর route ছিল, কিন্তু **`AutomationStep` তৈরি/এডিট/মোছার কোনো endpoint কোথাও ছিল না**। মানে API দিয়ে তৈরি করা প্রতিটা automation চিরকাল শূন্য step নিয়ে থাকত — `AutomationEngine::execute()` চালালেও বাস্তবে কিছুই হতো না, যদিও পুরো feature "reachable" ছিল।

**ফিক্স:** নতুন `AutomationStepController` (`store`/`update`/`destroy`), routes যোগ করা হয়েছে, প্রতিটাই parent automation-এর tenant দিয়ে scope করা।

## 4. Frontend
- **`AutomationsList.tsx`** (`/automations`): summary card (total/active/runs), তালিকা, নতুন automation তৈরি (draft হিসেবে শুরু হয়, "add steps and activate it from detail page" note সহ)
- **`AutomationDetail.tsx`**: status টগল (draft/active/paused/archived), step তালিকা + step যোগ/মোছা, Test run ও Execute now বাটন — দুটোই backend-এর raw JSON result সরাসরি দেখায়

## 5-8. Database/API/Security Changes
কোনো migration না। ২টা security fix (#2), ৩টা নতুন route (#3)।

## 9-11. Tests / Runtime Verification
**UNVERIFIED — npm/PHP নেই,** যথারীতি।

## 12. Known Issues
- `action_config`/`condition_config` (JSON) fields step form-এ নেই — শুধু action type + delay, কারণ actual config schema action type-ভেদে ভিন্ন হতে পারে এবং এটা একটা বড় আলাদা UI কাজ (dynamic form per action type)
- Step-এর drag-and-drop reordering নেই, শুধু create-time step_order

## 13. Unverified Items
পুরো ব্যাচের TypeScript compile/render, এবং security fix দুটো বাস্তবে migration/DB ছাড়া রান-টাইমে প্রমাণ করা যায়নি।

## 14. Bugs Fixed
Cross-tenant mass-assignment (#2, নিরাপত্তা-গুরুত্বপূর্ণ), missing step-creation endpoint (#3, functional)।

## 15. Regression Results
- Brace/paren balance (৬টা ফাইল): **PASS**
- Route collision check (`/automations/{automationId}/steps` বনাম apiResource-এর `/automations/{automation}`): **PASS**, path length আলাদা বলে conflict নেই
- Duplicate route name / duplicate table: **PASS**
- সম্পূর্ণ প্রজেক্টে একই মাস-অ্যাসাইনমেন্ট প্যাটার্নের আরও instance আছে কিনা: **PASS** (আর নেই)
- ⚪ TypeScript compile / PHP runtime: **UNVERIFIED**

## 16. Deployment Impact
কোনো migration না। Security fix production-এ যত দ্রুত সম্ভব deploy করা উচিত (যদিও exploit করতে valid JWT + সেই tenant-এর নিজের একটা automation/dashboard লাগত, unauthenticated attack না, কিন্তু insider/compromised-account ঝুঁকি real)।

## 17. Remaining Work
- Webhook admin UI (এখন একমাত্র বড় বাকি area)
- Dynamic action_config ফর্ম (per action type)
- Step reordering UI
- সবচেয়ে গুরুত্বপূর্ণ, অপরিবর্তিত: বাস্তব `npm`/`PHP` পরিবেশে প্রথমবার চালিয়ে দেখা
