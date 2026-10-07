<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\PredictionService;
use Illuminate\Http\Request;

/**
 * PHASE 12 AUDIT FINDING: PredictionService existed but had no
 * controller or route anywhere calling it — unreachable code, on top
 * of the bugs fixed in the service itself.
 */
class PredictionController extends Controller
{
    public function __construct(protected PredictionService $predictions)
    {
    }

    public function churnRisk(Request $request, $customerId)
    {
        $customer = Customer::where('tenant_id', $request->user->tenant_id)->findOrFail($customerId);
        $score = $this->predictions->predictChurnRisk($customer);

        return response()->json(['data' => ['customer_id' => $customer->id, 'churn_risk_score' => $score]]);
    }

    public function nextBookingValue(Request $request, $customerId)
    {
        $customer = Customer::where('tenant_id', $request->user->tenant_id)->findOrFail($customerId);
        $value = $this->predictions->predictNextBookingValue($customer);

        return response()->json(['data' => [
            'customer_id' => $customer->id,
            'predicted_next_booking_value' => $value,
            'note' => $value === null ? 'No booking history to project from.' : null,
        ]]);
    }

    public function popularDestination(Request $request)
    {
        $destination = $this->predictions->predictPopularDestination($request->user->tenant_id);

        return response()->json(['data' => [
            'most_booked_destination' => $destination,
            'note' => $destination === null ? 'No booking/package data available yet.' : null,
        ]]);
    }
}
