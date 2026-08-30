<?php
namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\UpsellRecommendation;
use App\Models\UpsellRule;
use App\Services\UpsellEngine;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpsellController extends Controller
{
    public function __construct(private UpsellEngine $engine)
    {
    }

    public function indexRules(Request $request)
    {
        return response()->json([
            'data' => UpsellRule::where('tenant_id', $request->user->tenant_id)->orderBy('priority')->get(),
            'trigger_types' => UpsellRule::TRIGGER_TYPES,
            'recommend_types' => UpsellRule::RECOMMEND_TYPES,
        ]);
    }

    public function storeRule(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'trigger_type' => ['required', Rule::in(UpsellRule::TRIGGER_TYPES)],
            'recommend_type' => ['required', Rule::in(UpsellRule::RECOMMEND_TYPES)],
            'description' => 'nullable|string',
            'suggested_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'priority' => 'nullable|integer|min:0',
            'requires_availability_check' => 'boolean',
        ]);

        $rule = UpsellRule::create([
            'tenant_id' => $request->user->tenant_id,
            'is_active' => true,
            ...$validated,
        ]);

        return response()->json(['data' => $rule], 201);
    }

    public function updateRule(Request $request, $id)
    {
        $rule = UpsellRule::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'suggested_price' => 'nullable|numeric|min:0',
            'priority' => 'sometimes|integer|min:0',
            'requires_availability_check' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $rule->update($validated);

        return response()->json(['data' => $rule]);
    }

    public function destroyRule(Request $request, $id)
    {
        $rule = UpsellRule::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $rule->delete();

        return response()->json(['message' => 'Upsell rule deleted']);
    }

    // Recommendations for a booking, filtered by real availability.
    public function forBooking(Request $request, $bookingId)
    {
        $booking = Booking::where('tenant_id', $request->user->tenant_id)
            ->with('package')
            ->findOrFail($bookingId);

        return response()->json(['data' => $this->engine->forBooking($booking)]);
    }

    public function recordShown(Request $request)
    {
        $validated = $request->validate([
            'rule_id' => 'required|exists:upsell_rules,id',
            'recommend_type' => 'required|string',
            'booking_id' => 'nullable|exists:bookings,id',
            'lead_id' => 'nullable|exists:leads,id',
        ]);

        $record = $this->engine->recordShown(
            $request->user->tenant_id,
            ['rule_id' => $validated['rule_id'], 'recommend_type' => $validated['recommend_type']],
            $validated['booking_id'] ?? null,
            $validated['lead_id'] ?? null,
            $request->user->id,
        );

        return response()->json(['data' => $record], 201);
    }

    public function recordOutcome(Request $request, $id)
    {
        $record = UpsellRecommendation::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'outcome' => ['required', Rule::in(['accepted', 'declined'])],
            'accepted_value' => 'nullable|numeric|min:0',
        ]);

        $record->update([
            'outcome' => $validated['outcome'],
            'accepted_value' => $validated['outcome'] === 'accepted' ? ($validated['accepted_value'] ?? null) : null,
            'responded_at' => now(),
        ]);

        return response()->json(['data' => $record]);
    }

    public function effectiveness(Request $request)
    {
        return response()->json(['data' => $this->engine->effectiveness($request->user->tenant_id)]);
    }
}
