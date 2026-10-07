<?php

namespace App\Http\Controllers;

use App\Models\UpsellOffer;
use App\Models\Booking;
use App\Models\Customer;
use App\Services\UpsellCrossSellService;
use Illuminate\Http\Request;

class UpsellController extends Controller
{
    public function __construct(protected UpsellCrossSellService $service)
    {
    }

    public function index(Request $request)
    {
        return response()->json(['data' => UpsellOffer::where('tenant_id', $request->user->tenant_id)->get()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|in:visa,insurance,hotel_upgrade,tour_guide,transfer,premium_package,additional_service',
            'applies_to_destination' => 'nullable|string',
            'applies_to_booking_type' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'description' => 'nullable|string',
        ]);

        $offer = UpsellOffer::create(['tenant_id' => $request->user->tenant_id, ...$validated]);
        return response()->json(['data' => $offer], 201);
    }

    public function update(Request $request, $id)
    {
        $offer = UpsellOffer::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'description' => 'nullable|string',
        ]);
        $offer->update($validated);
        return response()->json(['data' => $offer]);
    }

    public function delete(Request $request, $id)
    {
        UpsellOffer::where('tenant_id', $request->user->tenant_id)->findOrFail($id)->delete();
        return response()->json(['message' => 'Upsell offer deleted']);
    }

    public function forBooking(Request $request, $bookingId)
    {
        $booking = Booking::where('tenant_id', $request->user->tenant_id)->with('package')->findOrFail($bookingId);
        return response()->json(['data' => $this->service->recommendUpsells($booking)]);
    }

    public function crossSellForCustomer(Request $request, $customerId)
    {
        $customer = Customer::where('tenant_id', $request->user->tenant_id)->findOrFail($customerId);
        return response()->json(['data' => $this->service->recommendCrossSell($customer)]);
    }
}
