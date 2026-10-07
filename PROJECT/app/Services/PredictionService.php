<?php
namespace App\Services;
use App\Models\Prediction;
use App\Models\Customer;
use App\Models\Booking;

/**
 * PHASE 12 AUDIT FINDING: three real bugs here.
 * 1. predictNextBookingValue() queried Booking::avg('total_price') —
 *    that column doesn't exist (it's total_amount), so avg() always
 *    returned null, meaning the "?? 3000" fallback fired on every
 *    single call regardless of a customer's real booking history —
 *    every customer got the same fake predicted value.
 * 2. confidence_score was a flat hardcoded number (75, 68) for every
 *    prediction regardless of how much real data backed it.
 * 3. predictPopularDestination() returned a literal hardcoded string
 *    ('Saudi Arabia') — no query, no data, ever.
 */
class PredictionService
{
    public function predictChurnRisk(Customer $customer): float
    {
        $bookingCount = Booking::where('customer_id', $customer->id)->count();
        $riskScore = max(0, min(100, 100 - ($bookingCount * 5)));

        // Confidence reflects how much real history backs this score —
        // more bookings observed = more confidence in the pattern, capped
        // at 90 since this remains a simple heuristic, not a trained
        // statistical model with a real confidence interval.
        $confidence = min(90, 30 + ($bookingCount * 10));

        Prediction::create([
            'tenant_id' => $customer->tenant_id,
            'entity_type' => 'customer',
            'entity_id' => $customer->id,
            'prediction_type' => 'churn_risk',
            'predicted_value' => $riskScore,
            'confidence_score' => $confidence,
            'reasoning' => "Based on {$bookingCount} historical bookings",
            'predicted_at' => now()
        ]);

        return $riskScore;
    }

    public function predictNextBookingValue(Customer $customer): ?float
    {
        $bookingCount = Booking::where('customer_id', $customer->id)->count();
        $avgValue = Booking::where('customer_id', $customer->id)->avg('total_amount');

        if ($avgValue === null) {
            // No booking history at all — don't fabricate a number.
            return null;
        }

        $predicted = round($avgValue * 1.1, 2); // naive 10% growth assumption, stated as such
        $confidence = min(85, 20 + ($bookingCount * 15));

        Prediction::create([
            'tenant_id' => $customer->tenant_id,
            'entity_type' => 'customer',
            'entity_id' => $customer->id,
            'prediction_type' => 'next_booking_value',
            'predicted_value' => $predicted,
            'confidence_score' => $confidence,
            'reasoning' => "Naive +10% projection on average of {$bookingCount} historical bookings",
            'predicted_at' => now()
        ]);

        return $predicted;
    }

    public function predictPopularDestination(int $tenantId): ?string
    {
        $top = Booking::where('bookings.tenant_id', $tenantId)
            ->join('packages', 'bookings.package_id', '=', 'packages.id')
            ->selectRaw('packages.destination, count(*) as bookings_count')
            ->groupBy('packages.destination')
            ->orderByDesc('bookings_count')
            ->first();

        return $top?->destination; // null if there's no booking/package data at all
    }
}
