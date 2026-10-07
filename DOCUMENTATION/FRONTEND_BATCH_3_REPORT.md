# QUDRIX CRM — FRONTEND BATCH 3 REPORT (Hotels + Room Assignment)

**তারিখ:** September 20, 2026

## 1. Objective
আগের batch-এ ইচ্ছাকৃতভাবে ছেড়ে রাখা Room Assignment UI — যেটার জন্য প্রথমে একটা Hotel Booking জায়গা দরকার ছিল — সেটা এখন সম্পূর্ণ করা।

## 2. একটা real backend gap ধরা পড়েছে, ফিক্স করা হয়েছে
`Booking` মডেলে `hotelBookings()` relation-ই ছিল না, যদিও `HotelBooking::booking_id` ঠিকই সেট করা ছিল (`HotelController@bookHotel`-এ)। মানে `BookingController@show`-এর মাধ্যমে একটা booking-এর hotel stay দেখার কোনো উপায়ই ছিল না। এটা যোগ করা হয়েছে এবং `BookingController@show`-এর eager-load-এ (`hotelBookings.hotel`, `hotelBookings.roomAssignments.travelers`) যুক্ত করা হয়েছে।

**গুরুত্বপূর্ণ টেকনিক্যাল নোট:** Laravel-এ camelCase relation method (`hotelBookings`, `roomAssignments`) JSON-এ output হওয়ার সময় automatically snake_case হয় না — exact method name-ই key থাকে। তাই frontend TypeScript interface-এ ইচ্ছাকৃতভাবে `hotelBookings`/`roomAssignments` (camelCase) রাখা হয়েছে, `hotel_bookings`/`room_assignments` না — এটা ভুল করে snake_case লিখলে silent data-mismatch bug হতো (JS-এ `undefined`, কোনো error ছাড়াই)।

## 3. যা বানানো হয়েছে
- **`HotelsList.tsx`** (নতুন `/hotels`): hotel browse + create — এটা booking-এ hotel সংযুক্ত করার আগে দরকার ছিল, তাই আগে বানানো
- **`BookingDetail.tsx` সম্প্রসারণ**: "Hotel stays & room assignment" section — hotel বুক করার ফর্ম, প্রতিটা hotel stay-এর নিচে room যোগ করার ফর্ম, প্রতিটা room-এ checkbox দিয়ে traveler assign/unassign করে "Save room occupants" — যা backend-এর max_occupancy validation-কে সরাসরি hit করে (over-capacity হলে alert দেখায়, silently fail করে না)
- Nav-এ "Hotels" আইটেম যোগ

## 4. Files Changed
নতুন: `pages/hotels/HotelsList.tsx`
পরিবর্তিত (backend): `app/Models/Booking.php` (+hotelBookings relation), `app/Http/Controllers/BookingController.php` (eager-load বাড়ানো)
পরিবর্তিত (frontend): `pages/bookings/BookingDetail.tsx`, `App.tsx`, `layouts/AppLayout.tsx`

## 5-8. Database/API/Security Changes
কোনো নতুন migration না — শুধু একটা মিসিং Eloquent relation যোগ (#2)। নতুন কোনো route লাগেনি, existing `/hotels`, `/hotels/book`, `/room-assignments`, `/room-assignments/{id}/travelers` ব্যবহার করা হয়েছে।

## 9-11. Tests / Runtime Verification
**UNVERIFIED — npm নেই।** এই ব্যাচে backend-এ একটা সত্যিকারের কোড পরিবর্তন হয়েছে (relation যোগ) — সেটাও PHP না চালিয়ে static review-এই আটকে আছে। brace/paren balance আর route-existence cross-check করা হয়েছে যথারীতি।

## 12. Known Issues
- Room assignment-এর checkbox default state room-এর বিদ্যমান traveler list থেকে নেওয়া, কিন্তু state ম্যানেজমেন্ট simplistic (প্রতিটা room-এর জন্য আলাদা local override array) — অনেকগুলো room থাকলে UX আরও ভালো করা যেত, কিন্তু কার্যকরী
- Hotel booking form-এ available_rooms কমে যাওয়ার real-time reflection নেই (page reload/re-fetch লাগবে)

## 13. Unverified Items
পুরো ব্যাচের TypeScript compile/render, আর backend-এর নতুন relation আসলে সঠিক ডেটা রিটার্ন করে কিনা — env limitation।

## 14. Bugs Fixed
`Booking` মডেলে মিসিং `hotelBookings()` relation (#2)।

## 15. Regression Results
- Brace/paren balance (৪টা নতুন/পরিবর্তিত frontend ফাইল + ২টা backend ফাইল): **PASS**
- Duplicate route path (frontend + backend, `/hotels/book` বনাম apiResource shadowing চেক): **PASS**
- Duplicate table/route (backend, প্রজেক্ট-জোড়া): **PASS**
- ⚪ TypeScript compile / PHP runtime: **UNVERIFIED**

## 16. Deployment Impact
কিছুই না — শুধু একটা Eloquent relation, কোনো schema change না।

## 17. Remaining Work
এখন প্রতিটা P0/P1/P2 feature-এর অন্তত কিছু UI আছে। বাকি: Student Visa/Al-Azhar UI, TrackingConfig page, Suppliers/Flights/Transport UI, multi-tenant settings, AI, Automation, Complaints, Reports, Webhooks admin UI — এবং সবসময়ের মতো, বাস্তব `npm`/`php` পরিবেশে verify করা।
