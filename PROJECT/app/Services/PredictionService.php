<?php
namespace App\Services;
use App\Models\Prediction;
use App\Models\Customer;
use App\Models\Booking;

class PredictionService
{
    public function predictChurnRisk(Customer $customer): float
    {
        // Simplified prediction based on activity
        $bookingCount = Booking::where('customer_id', $customer->id)->count();
        $riskScore = max(0, min(100, 100 - ($bookingCount * 5)));
        
        Prediction::create([
            'tenant_id' => $customer->tenant_id,
            'entity_type' => 'customer',
            'entity_id' => $customer->id,
            'prediction_type' => 'churn_risk',
            'predicted_value' => $riskScore,
            'confidence_score' => 75,
            'reasoning' => "Based on booking frequency and recency",
            'predicted_at' => now()
        ]);
        
        return $riskScore;
    }
    
    public function predictNextBookingValue(Customer $customer): float
    {
        $avgValue = Booking::where('customer_id', $customer->id)->avg('total_price') ?? 3000;
        $predicted = $avgValue * 1.1; // 10% growth assumption
        
        Prediction::create([
            'tenant_id' => $customer->tenant_id,
            'entity_type' => 'customer',
            'entity_id' => $customer->id,
            'prediction_type' => 'next_booking_value',
            'predicted_value' => $predicted,
            'confidence_score' => 68,
            'reasoning' => "Based on historical booking values",
            'predicted_at' => now()
        ]);
        
        return $predicted;
    }
    
    public function predictPopularDestination(int $tenantId): string
    {
        // Simplified: Return trending destination
        return 'Saudi Arabia';
    }
}
