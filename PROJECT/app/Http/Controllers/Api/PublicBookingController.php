<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Public Booking Controller
 * Used by website to create bookings
 */
class PublicBookingController extends Controller
{
    /**
     * Create a new booking from website
     * 
     * POST /api/v1/bookings
     * 
     * Body:
     * {
     *   "package_id": 1,
     *   "customer": {
     *     "name": "John Doe",
     *     "email": "john@example.com",
     *     "phone": "1234567890",
     *     "address": "123 Street"
     *   },
     *   "travelers": [
     *     {
     *       "name": "John Doe",
     *       "age": 30,
     *       "passport": "AB123456"
     *     }
     *   ],
     *   "travel_date": "2024-12-01",
     *   "special_requests": "Non-vegetarian",
     *   "source": "website"
     * }
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'package_id' => 'required|integer|exists:packages,id',
                'customer.name' => 'required|string|min:2|max:255',
                'customer.email' => 'required|email',
                'customer.phone' => 'required|string|min:7|max:20',
                'customer.address' => 'nullable|string|max:500',
                'travelers' => 'required|array|min:1',
                'travelers.*.name' => 'required|string|min:2|max:255',
                'travelers.*.age' => 'required|integer|min:1|max:120',
                'travelers.*.passport' => 'nullable|string|max:50',
                'travel_date' => 'required|date|after:today',
                'special_requests' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'code' => 'VALIDATION_ERROR',
                    'errors' => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Get package
            $package = Package::where('id', $request->package_id)
                ->where('is_active', true)
                ->first();

            if (!$package) {
                return response()->json([
                    'success' => false,
                    'message' => 'Package not found',
                    'code' => 'PACKAGE_NOT_FOUND',
                ], Response::HTTP_NOT_FOUND);
            }

            // Check capacity
            if ($package->bookings_count >= $package->capacity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Package is fully booked',
                    'code' => 'NO_CAPACITY',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Find or create customer
            $customer = Customer::where('email', $request->customer['email'])->first();
            
            if (!$customer) {
                $customer = Customer::create([
                    'name' => $request->customer['name'],
                    'email' => $request->customer['email'],
                    'phone' => $request->customer['phone'],
                    'address' => $request->customer['address'] ?? null,
                    'source' => 'website',
                    'status' => 'lead',
                ]);
            }

            // Create booking
            $booking = Booking::create([
                'booking_reference' => 'BK-' . Str::random(10),
                'package_id' => $package->id,
                'customer_id' => $customer->id,
                'travel_date' => $request->travel_date,
                'number_of_travelers' => count($request->travelers),
                'total_price' => $package->price * count($request->travelers),
                'payment_status' => 'pending',
                'booking_status' => 'pending',
                'special_requests' => $request->special_requests ?? null,
                'source' => 'website',
                'created_by' => 'website_api',
            ]);

            // Store travelers
            foreach ($request->travelers as $traveler) {
                $booking->travelers()->create([
                    'name' => $traveler['name'],
                    'age' => $traveler['age'],
                    'passport_number' => $traveler['passport'] ?? null,
                    'passport_expiry' => null,
                ]);
            }

            // Log API usage
            \Log::info('Booking created via public API', [
                'booking_id' => $booking->id,
                'package_id' => $package->id,
                'customer_email' => $customer->email,
                'source' => 'website',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully',
                'data' => [
                    'id' => $booking->id,
                    'booking_reference' => $booking->booking_reference,
                    'status' => $booking->booking_status,
                    'payment_status' => $booking->payment_status,
                    'total_price' => $booking->total_price,
                    'currency' => 'BDT',
                    'travel_date' => $booking->travel_date->toIso8601String(),
                    'created_at' => $booking->created_at->toIso8601String(),
                    'confirmation_email_sent' => true,
                ],
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'api_version' => 'v1',
                    'next_step' => 'Customer will receive confirmation email. Payment link will be sent shortly.',
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            \Log::error('Public booking creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create booking',
                'code' => 'CREATION_ERROR',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get booking status by reference
     * 
     * GET /api/v1/bookings/{reference}
     * 
     * @param string $reference
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($reference)
    {
        try {
            $booking = Booking::where('booking_reference', $reference)->first();

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found',
                    'code' => 'NOT_FOUND',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $booking->id,
                    'booking_reference' => $booking->booking_reference,
                    'package_name' => $booking->package->name ?? null,
                    'customer_name' => $booking->customer->name ?? null,
                    'status' => $booking->booking_status,
                    'payment_status' => $booking->payment_status,
                    'total_price' => $booking->total_price,
                    'currency' => 'BDT',
                    'travel_date' => $booking->travel_date->toIso8601String(),
                    'number_of_travelers' => $booking->number_of_travelers,
                    'travelers' => $booking->travelers->map(fn($t) => [
                        'name' => $t->name,
                        'age' => $t->age,
                    ]),
                    'special_requests' => $booking->special_requests,
                    'created_at' => $booking->created_at->toIso8601String(),
                    'updated_at' => $booking->updated_at->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch booking',
                'code' => 'FETCH_ERROR',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
