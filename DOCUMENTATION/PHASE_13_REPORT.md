# QUDRIX Travel CRM — PHASE 13 রিপোর্ট
## Upsell/Cross-sell + A/B Testing

**তারিখ:** September 2, 2026
**Environment note (অপরিবর্তিত):** এই sandbox-এ PHP/MySQL/network নেই।

---

## 1. Audit-এর ফলাফল
সম্পূর্ণ greenfield - কোনো upsell/cross-sell/experiment/variant সম্পর্কিত কিছুই ছিল না।

## 2. এই ফেজের সিদ্ধান্ত: সবকিছুই deterministic, AI ছাড়াই
Upsell/Cross-sell আর A/B Testing - দুটোই বাস্তবে সম্পূর্ণ real ডেটা দিয়ে কাজ করতে পারে, কোনো AI দরকার হয় না:

- Upsell recommendations - tenant-configured UpsellOffer থেকে booking-এর destination/booking_type মিলিয়ে filter করা, এবং booking-এ যা ইতিমধ্যে আছে (visa_required flag) তা বাদ দেওয়া।
- Cross-sell - customer-এর real booking history থেকে destination বের করে, সেই destination-এর অন্য real প্যাকেজ suggest করা যা তারা এখনো বুক করেনি।
- A/B Testing - Experiment + ExperimentVariant, প্রতিটা view/engagement/conversion/revenue event বাস্তবে গোনা হয়।

## 3. Winning variant নির্ধারণে সততার সীমারেখা
"Winning variant" বের করার সময় ইচ্ছাকৃতভাবে রক্ষণশীল থেকেছি:
- প্রতিটা variant-এর কমপক্ষে ৩০টা view না হলে eligible ধরা হয় না।
- দুটো শীর্ষ variant-এর conversion rate পার্থক্য ২ শতাংশ পয়েন্টের কম হলে "no clear winner" বলা হয়।
- কোনো পরিসংখ্যানগত significance test প্রয়োগ করা হয়নি - simple threshold-ভিত্তিক নিয়ম, "statistically proven" দাবি করা হয়নি।

## 4. যা এই ফেজে বানানো হয়নি, এবং কেন
- Behavior-based cross-sell - কোনো clickstream/browsing-behavior ডেটা এই স্কিমায় ট্র্যাক করা হয় না।
- A/B test-এর জন্য automatic traffic-splitting - এটা frontend/presentation লেয়ারের কাজ (Phase 17)। এই ফেজে ব্যাকএন্ড ট্র্যাকিং+ফলাফল বিশ্লেষণ কাঠামো বানানো হয়েছে।

## 5. Regression check
পুরো app/Services/ এবং app/Http/Controllers/ আবার hardcoded fake-metric প্যাটার্নের জন্য স্ক্যান করা হয়েছে - নতুন কিছু পাওয়া যায়নি। controller/route resolution audit, model-to-table cross-check, migration ordering - সব পরিষ্কার।

## 6. Verified / Unverified / Blocked

VERIFIED: Upsell/Cross-sell এবং A/B test লজিক - সম্পূর্ণ deterministic, AI ছাড়াই কাজ করার কথা।
UNVERIFIED: real ডেটাবেসে execute করে দেখা এখনো বাকি।
BLOCKED: কিছু নেই।

## 7. Verification - নিজে চালিয়ে দেখুন

```bash
cd PROJECT
composer install && cp .env.example .env
php artisan key:generate && php artisan jwt:secret
php artisan migrate:fresh
php artisan serve

curl -X POST localhost:8000/upsell-offers -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" \
  -d '{"name":"Travel Insurance","category":"insurance","price":49.99}'
curl localhost:8000/bookings/1/upsell-recommendations -H "Authorization: Bearer <TOKEN>"

curl -X POST localhost:8000/experiments -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" \
  -d '{"name":"CTA test","subject_type":"cta","variants":[{"name":"A","content":"Book Now"},{"name":"B","content":"Reserve Your Spot"}]}'
curl -X POST localhost:8000/experiment-variants/1/track -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" -d '{"event":"view"}'
curl localhost:8000/experiments/1/results -H "Authorization: Bearer <TOKEN>"
```

## 8. পরবর্তী ফেজ
এখানে থামছি। পরেরটা Phase 14 (Complaint Handling + Automation) - spec-এই লেখা "AI must not promise refunds/compensation unless company rules explicitly allow it" - এটা Phase 3-এর approval-threshold প্যাটার্নের সাথে মিলে যায়।
