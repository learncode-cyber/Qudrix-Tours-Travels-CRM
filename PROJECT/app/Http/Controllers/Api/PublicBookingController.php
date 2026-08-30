<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\Customer;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

// Booking creation from a tenant's own website.
//
// REWRITTEN: the previous version wrote to columns that do not exist
// (`booking_reference`, `total_price`, `booking_status`), passed the string
// 'website_api' into `created_by` (a non-nullable integer FK to `users`),
// created travellers with a `name` field the table does not have, and applied
// no tenant filter anywhere. It could not have inserted a single row.
class PublicBookingController extends Controller
{
    private function tenantId(Request $request): ?int
    {
        return $request->apiKey->tenant_id ?? null;
    }

    public function store(Request $request)
    {
        $tenantId = $this->tenantId($request);
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'API key is not bound to a tenant.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'package_id' => 'required|integer',
            'travel_date' => 'required|date|after:today',
            'return_date' => 'nullable|date|after:travel_date',
            'customer' => 'required|array',
            'customer.name' => 'required|string|max:255',
            'customer.email' => 'required|email',
            'customer.phone' => 'required|string|max:32',
            'customer.address' => 'nullable|string',
            'travelers' => 'required|array|min:1',
            'travelers.*.first_name' => 'required|string|max:120',
            'travelers.*.last_name' => 'required|string|max:120',
            'travelers.*.email' => 'nullable|email',
            'travelers.*.phone' => 'nullable|string|max:32',
            'travelers.*.date_of_birth' => 'nullable|date',
            'travelers.*.passport_number' => 'nullable|string|max:64',
            'special_requests' => 'nullable|array',
        ]);

        // The package must belong to the API key's tenant — this is what
        // stops one tenant's key from booking another tenant's inventory.
        $package = Package::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->find($validated['package_id']);

        if (!$package) {
            return response()->json([
                'success' => false,
                'message' => 'Package not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $booking = DB::transaction(function () use ($tenantId, $validated, $package) {
            // Customer lookup is scoped to the tenant, so a shared email
            // address cannot link a booking to another tenant's customer.
            $customer = Customer::where('tenant_id', $tenantId)
                ->where('email', $validated['customer']['email'])
                ->first();

            if (!$customer) {
                $customer = Customer::create([
                    'tenant_id' => $tenantId,
                    'name' => $validated['customer']['name'],
                    'email' => $validated['customer']['email'],
                    'phone' => $validated['customer']['phone'],
                    'address' => $validated['customer']['address'] ?? null,
                    'customer_type' => 'individual',
                    'source' => 'website',
                    'is_active' => true,
                    'status' => 'active',
                ]);
            }

            $travellerCount = count($validated['travelers']);

            $booking = Booking::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'package_id' => $package->id,
                // No CRM user creates a website booking; the column is
                // nullable and `source` records where it came from.
                'created_by' => null,
                'source' => 'website',
                'booking_number' => 'BK-' . now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
                'booking_type' => 'individual',
                'status' => 'pending',
                'payment_status' => 'pending',
                'travel_date' => $validated['travel_date'],
                'return_date' => $validated['return_date'] ?? null,
                'number_of_travelers' => $travellerCount,
                // Price comes from the package's real base_price, never
                // from the request.
                'total_amount' => round((float) $package->base_price * $travellerCount, 2),
                'currency' => 'USD',
                'special_requests' => $validated['special_requests'] ?? null,
            ]);

            foreach ($validated['travelers'] as $index => $traveler) {
                BookingTraveler::create([
                    'booking_id' => $booking->id,
                    'first_name' => $traveler['first_name'],
                    'last_name' => $traveler['last_name'],
                    'email' => $traveler['email'] ?? null,
                    'phone' => $traveler['phone'] ?? null,
                    'date_of_birth' => $traveler['date_of_birth'] ?? null,
                    'passport_number' => $traveler['passport_number'] ?? null,
                    'traveler_type' => 'adult',
                    'is_primary_contact' => $index === 0,
                ]);
            }

            return $booking;
        });

        return response()->json([
            'success' => true,
            'message' => 'Booking created and is pending confirmation by the agency.',
            'data' => [
                'booking_number' => $booking->booking_number,
                'status' => $booking->status,
                'payment_status' => $booking->payment_status,
                'total_amount' => (float) $booking->total_amount,
                'currency' => $booking->currency,
                'travel_date' => optional($booking->travel_date)->toDateString(),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, $reference)
    {
        $tenantId = $this->tenantId($request);
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'API key is not bound to a tenant.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $booking = Booking::where('tenant_id', $tenantId)
            ->where('booking_number', $reference)
            ->with('travelers')
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'booking_number' => $booking->booking_number,
                'status' => $booking->status,
                'payment_status' => $booking->payment_status,
                'travel_date' => optional($booking->travel_date)->toDateString(),
                'return_date' => optional($booking->return_date)->toDateString(),
                'number_of_travelers' => $booking->number_of_travelers,
                'total_amount' => (float) $booking->total_amount,
                'currency' => $booking->currency,
                'travelers' => $booking->travelers->map(fn ($t) => [
                    'first_name' => $t->first_name,
                    'last_name' => $t->last_name,
                ])->all(),
            ],
        ]);
    }
}
