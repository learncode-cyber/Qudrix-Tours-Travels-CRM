<?php
namespace App\Services;

use App\Models\Booking;
use App\Models\Flight;
use App\Models\Hotel;
use App\Models\Transport;
use App\Models\UpsellRecommendation;
use App\Models\UpsellRule;

// Rule-based upsell / cross-sell engine (Directive S11).
//
// Recommendations come from admin-configured rules, and a rule marked
// `requires_availability_check` is only surfaced when there is genuinely
// something available to sell. That is the difference between a real
// recommendation and a suggestion the rep cannot actually fulfil.
class UpsellEngine
{
    /**
     * Recommendations for a booking, based on what it already contains.
     */
    public function forBooking(Booking $booking): array
    {
        $triggers = $this->triggersFor($booking);

        $rules = UpsellRule::where('tenant_id', $booking->tenant_id)
            ->where('is_active', true)
            ->where(function ($q) use ($triggers) {
                $q->whereIn('trigger_type', $triggers)->orWhere('trigger_type', 'any');
            })
            ->orderBy('priority')
            ->get();

        $recommendations = [];

        foreach ($rules as $rule) {
            // Do not recommend something the booking already has.
            if (in_array($rule->recommend_type, $triggers, true)) {
                continue;
            }

            $availability = $rule->requires_availability_check
                ? $this->checkAvailability($booking->tenant_id, $rule->recommend_type)
                : ['available' => true, 'count' => null, 'note' => 'Availability check not required for this rule'];

            if (!$availability['available']) {
                // Deliberately skipped rather than shown as an option the
                // rep cannot deliver.
                continue;
            }

            $recommendations[] = [
                'rule_id' => $rule->id,
                'name' => $rule->name,
                'recommend_type' => $rule->recommend_type,
                'description' => $rule->description,
                'suggested_price' => $rule->suggested_price !== null ? (float) $rule->suggested_price : null,
                'currency' => $rule->currency,
                'availability' => $availability,
            ];
        }

        return [
            'booking_id' => $booking->id,
            'detected_components' => $triggers,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Records that a recommendation was shown, so conversion can later be
     * measured from real outcomes.
     */
    public function recordShown(int $tenantId, array $recommendation, ?int $bookingId, ?int $leadId, ?int $userId): UpsellRecommendation
    {
        return UpsellRecommendation::create([
            'tenant_id' => $tenantId,
            'upsell_rule_id' => $recommendation['rule_id'] ?? null,
            'booking_id' => $bookingId,
            'lead_id' => $leadId,
            'shown_by' => $userId,
            'recommend_type' => $recommendation['recommend_type'],
            'outcome' => 'shown',
        ]);
    }

    /**
     * Real effectiveness stats — accepted / shown, per recommendation type.
     */
    public function effectiveness(int $tenantId): array
    {
        $rows = UpsellRecommendation::where('tenant_id', $tenantId)
            ->selectRaw("recommend_type,
                COUNT(*) as shown,
                SUM(CASE WHEN outcome = 'accepted' THEN 1 ELSE 0 END) as accepted,
                SUM(CASE WHEN outcome = 'accepted' THEN accepted_value ELSE 0 END) as accepted_value")
            ->groupBy('recommend_type')
            ->get();

        return $rows->map(function ($r) {
            $shown = (int) $r->shown;
            $accepted = (int) $r->accepted;
            return [
                'recommend_type' => $r->recommend_type,
                'shown' => $shown,
                'accepted' => $accepted,
                'acceptance_rate_percent' => $shown > 0 ? round(($accepted / $shown) * 100, 2) : null,
                'revenue_from_upsells' => round((float) $r->accepted_value, 2),
            ];
        })->all();
    }

    /**
     * What this booking already includes, used both to pick rules and to
     * avoid recommending something already present.
     */
    private function triggersFor(Booking $booking): array
    {
        $triggers = [];

        if ($booking->booking_type) {
            $triggers[] = $booking->booking_type;
        }
        if ($booking->package && $booking->package->type) {
            $triggers[] = $booking->package->type;
        }
        if ($booking->visa_required) {
            $triggers[] = 'visa';
        }
        if ($booking->relationLoaded('travelers') || $booking->travelers()->exists()) {
            // no-op: presence of travelers does not imply a component type
        }

        // Real component presence, from the join tables.
        if (\App\Models\FlightBooking::where('booking_id', $booking->id)->exists()) {
            $triggers[] = 'flight';
        }
        if (\App\Models\HotelBooking::where('booking_id', $booking->id)->exists()) {
            $triggers[] = 'hotel';
        }
        if (\App\Models\VisaApplication::where('booking_id', $booking->id)->exists()) {
            $triggers[] = 'visa';
        }

        return array_values(array_unique(array_filter($triggers)));
    }

    /**
     * Real inventory check. Types the CRM does not hold inventory for
     * (insurance, tour guide, generic add-ons) are reported honestly as
     * "not tracked as inventory" rather than as available or unavailable.
     */
    private function checkAvailability(int $tenantId, string $recommendType): array
    {
        switch ($recommendType) {
            case 'hotel':
                $count = Hotel::where('tenant_id', $tenantId)->where('status', 'active')->count();
                return ['available' => $count > 0, 'count' => $count, 'note' => null];

            case 'flight':
                $count = Flight::where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->where('available_seats', '>', 0)
                    ->count();
                return ['available' => $count > 0, 'count' => $count, 'note' => null];

            case 'transport':
                $count = Transport::where('tenant_id', $tenantId)->count();
                return ['available' => $count > 0, 'count' => $count, 'note' => null];

            case 'visa':
                // Visa is a service the agency performs, not stock it holds.
                return ['available' => true, 'count' => null, 'note' => 'Visa is a service, not inventory-tracked'];

            default:
                // insurance, tour_guide, addon — no inventory model exists.
                return [
                    'available' => true,
                    'count' => null,
                    'note' => "'{$recommendType}' is not tracked as inventory in this system; confirm with the supplier before promising it",
                ];
        }
    }
}
