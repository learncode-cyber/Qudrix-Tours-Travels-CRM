# QUDRIX CRM — FRONTEND BATCH 6 REPORT (Flight/Transport Booking Flow)

**তারিখ:** September 20, 2026

## 1. Objective
আগের batch-এ ইচ্ছাকৃতভাবে ছেড়ে রাখা কাজ শেষ করা: `BookingDetail.tsx`-এ flight ও transport booking যোগ করা, ঠিক যেভাবে hotel booking আগেই যুক্ত হয়েছিল।

## 2. আরেকটা একই প্যাটার্নের backend gap ধরা পড়েছে ও ফিক্স হয়েছে
`hotelBookings()`-এর মতোই, `Booking` মডেলে `flightBookings()` আর `transportBookings()` relation-ও ছিল না — যদিও `FlightBooking`/`TransportBooking`-এর `booking_id` ঠিকই সেট হতো (`FlightController@bookFlight`, `TransportController@bookTransport`)। দুটো relation-ই যোগ করা হয়েছে এবং `BookingController@show`-এর eager-load-এ (`flightBookings.flight`, `flightBookings.traveler`, `transportBookings.transport`, `transportBookings.traveler`) যুক্ত করা হয়েছে। এটা তৃতীয়বার এই একই ধরনের gap (hotel, এখন flight+transport) — যা নির্দেশ করে booking-এর "child resource" relation-গুলো তৈরির সময় ব্যবহারযোগ্যতা (retrieval) বিবেচনা না করেই শুধু create-side লজিক বানানো হয়েছিল।

## 3. যা বানানো হয়েছে
`BookingDetail.tsx`-এ দুটো নতুন card section:
- **Flights**: বিদ্যমান flight booking তালিকা (seat number-সহ), flight select + checkbox দিয়ে traveler বেছে বুক করার ফর্ম — কমপক্ষে একজন traveler না বাছলে বাটন disabled থাকে
- **Transport**: একই প্যাটার্ন, vehicle select + traveler checkbox

দুটোই backend-এর error response (not enough seats/capacity) সরাসরি দেখায়, silently ignore করে না।

## 4. Files Changed
পরিবর্তিত (backend): `app/Models/Booking.php` (+flightBookings, +transportBookings), `app/Http/Controllers/BookingController.php` (eager-load বাড়ানো)
পরিবর্তিত (frontend): `pages/bookings/BookingDetail.tsx`

## 5-8. Database/API/Security Changes
কোনো migration না — শুধু ২টা মিসিং relation যোগ। Route আগে থেকেই ছিল (`/flights/book`, `/transports/book`)।

## 9-11. Tests / Runtime Verification
**UNVERIFIED — npm/PHP নেই,** যথারীতি। brace/paren balance আর endpoint-existence cross-check করা হয়েছে।

## 12. Known Issues
- `BookingDetail.tsx` এখন বেশ বড় একটা ফাইল হয়ে গেছে (Mahram + Installment + Hotel/Room + Flight + Transport, ৬৯০ লাইনের কাছাকাছি) — ভবিষ্যতে আলাদা sub-component-এ ভাগ করা উচিত (maintainability), কিন্তু functionally ঠিক আছে
- Flight/transport booking form-এ available_seats/capacity real-time কমে যাওয়ার reflection নেই (hotel-এর মতোই, page reload/re-fetch লাগবে)

## 13. Unverified Items
পুরো ব্যাচের TypeScript compile/render, backend-এর নতুন relation-দুটো আসলে সঠিক ডেটা দেয় কিনা।

## 14. Bugs Fixed
`Booking` মডেলে মিসিং `flightBookings()`/`transportBookings()` relation (#2)।

## 15. Regression Results
- Brace/paren balance (`BookingDetail.tsx` + ২টা backend ফাইল): **PASS**
- Called backend endpoint existence: **PASS**
- ⚪ TypeScript compile / PHP runtime: **UNVERIFIED**

## 16. Deployment Impact
কিছুই না — শুধু Eloquent relation, কোনো schema change না।

## 17. Remaining Work
- `BookingDetail.tsx` refactor করে ছোট sub-component-এ ভাগ করা (maintainability, functional bug না)
- Multi-tenant settings, AI, Automation, Complaints, Reports, Webhooks admin UI — এখনো কোনো UI নেই
- সবচেয়ে গুরুত্বপূর্ণ, অপরিবর্তিত: বাস্তব `npm`/`PHP` পরিবেশে প্রথমবার চালিয়ে দেখা
