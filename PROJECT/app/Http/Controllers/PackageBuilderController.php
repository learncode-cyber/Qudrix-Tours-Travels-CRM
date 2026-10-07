<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Quotation;
use App\Models\Lead;
use App\Services\PricingEngineService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * PHASE 6: Custom Package Builder.
 *
 * IMPORTANT SCOPE NOTE: the phase spec's example ("I want a 10-day Egypt
 * trip for 5 people under $3000") describes a natural-language interface,
 * which needs an actual AI provider to parse free text into structured
 * parameters. No AI provider exists yet — that's Phase 9 per your own
 * roadmap. Building a fake "understands your sentence" endpoint that
 * doesn't actually call an AI would be exactly the kind of fake feature
 * this project's rules explicitly forbid.
 *
 * What IS built here, for real: everything downstream of understanding
 * the request — matching packages against structured criteria, running
 * the real PricingEngineService (no invented prices — every package
 * queried is a real row with a real, tenant-configured price), ranking
 * against budget, and generating a real Quotation. Once Phase 9 exists,
 * its job is simply to turn free text into the same structured params
 * this controller already accepts — this controller does not need to
 * change.
 */
class PackageBuilderController extends Controller
{
    public function __construct(protected PricingEngineService $pricingEngine)
    {
    }

    /**
     * Structured package search + real pricing. Never invents
     * availability or price — only returns packages that exist in the
     * database, priced through the real pricing engine.
     */
    public function recommend(Request $request)
    {
        $validated = $request->validate([
            'destination' => 'nullable|string',
            'number_of_travelers' => 'required|integer|min:1',
            'budget_max' => 'nullable|numeric|min:0',
            'travel_date' => 'nullable|date',
            'min_days' => 'nullable|integer|min:1',
            'max_days' => 'nullable|integer|min:1',
        ]);

        $tenantId = $request->user->tenant_id;

        $query = Package::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('status', 'active');

        if (!empty($validated['destination'])) {
            $query->where('destination', 'like', '%' . $validated['destination'] . '%');
        }
        if (!empty($validated['min_days'])) {
            $query->where('days', '>=', $validated['min_days']);
        }
        if (!empty($validated['max_days'])) {
            $query->where('days', '<=', $validated['max_days']);
        }

        $packages = $query->get();

        $priced = $packages->map(function (Package $package) use ($validated) {
            $pricing = $this->pricingEngine->calculate($package, $validated);

            return [
                'package' => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'destination' => $package->destination,
                    'days' => $package->days,
                    'nights' => $package->nights,
                    'inclusions' => $package->inclusions,
                    'exclusions' => $package->exclusions,
                ],
                'pricing' => collect($pricing)->except('internal')->all(),
                'fits_budget' => empty($validated['budget_max']) || $pricing['final_price'] <= $validated['budget_max'],
            ];
        });

        $fitting = $priced->where('fits_budget', true)->sortBy('pricing.final_price')->values();
        $overBudget = $priced->where('fits_budget', false)->sortBy('pricing.final_price')->values();

        return response()->json([
            'data' => [
                'matching_within_budget' => $fitting,
                'alternatives_over_budget' => $overBudget,
            ],
            'meta' => [
                'total_packages_evaluated' => $priced->count(),
                'search_criteria' => $validated,
            ],
        ]);
    }

    /**
     * Generates a real, saved Quotation for a chosen package — the
     * output of the builder flow, reusing Phase 3's quotation
     * infrastructure rather than a parallel one.
     */
    public function buildQuotation(Request $request)
    {
        $validated = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'lead_id' => 'nullable|exists:leads,id',
            'number_of_travelers' => 'required|integer|min:1',
            'travel_date' => 'nullable|date',
        ]);

        $tenantId = $request->user->tenant_id;
        $package = Package::where('tenant_id', $tenantId)->findOrFail($validated['package_id']);

        $pricing = $this->pricingEngine->calculate($package, $validated);

        $lead = !empty($validated['lead_id'])
            ? Lead::where('tenant_id', $tenantId)->findOrFail($validated['lead_id'])
            : null;

        if (!$lead) {
            return response()->json(['error' => 'lead_id is required to save a quotation'], 400);
        }

        $quotation = Quotation::create([
            'tenant_id' => $tenantId,
            'lead_id' => $lead->id,
            'package_id' => $package->id,
            'created_by' => $request->user->id,
            'quotation_number' => 'QT-' . Str::upper(Str::random(10)),
            'subject' => "Custom package: {$package->name}",
            'travel_date' => $validated['travel_date'] ?? null,
            'number_of_travelers' => $validated['number_of_travelers'],
            'subtotal' => $pricing['subtotal'],
            'discount_amount' => max(0, $pricing['subtotal'] - $pricing['final_price']),
            'total_amount' => $pricing['final_price'],
            'currency' => $pricing['currency'],
            'status' => 'draft',
            'valid_until' => now()->addDays(7),
        ]);

        $quotation->items()->create([
            'package_id' => $package->id,
            'description' => $package->name,
            'quantity' => $validated['number_of_travelers'],
            'unit_price' => $pricing['price_per_person'],
            'tax_rate' => 0,
            'discount' => 0,
            'total' => $pricing['final_price'],
        ]);

        return response()->json([
            'message' => 'Quotation generated from package builder',
            'data' => $quotation->load('items'),
            'pricing_breakdown' => collect($pricing)->except('internal')->all(),
        ], 201);
    }
}
