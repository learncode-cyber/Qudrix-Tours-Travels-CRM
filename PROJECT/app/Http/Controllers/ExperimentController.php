<?php

namespace App\Http\Controllers;

use App\Models\Experiment;
use App\Models\ExperimentVariant;
use Illuminate\Http\Request;

class ExperimentController extends Controller
{
    public function index(Request $request)
    {
        $experiments = Experiment::where('tenant_id', $request->user->tenant_id)
            ->with('variants', 'winningVariant')
            ->get();

        return response()->json(['data' => $experiments]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject_type' => 'required|in:sales_script,offer,pricing_presentation,package_presentation,cta,follow_up_message',
            'variants' => 'required|array|min:2',
            'variants.*.name' => 'required|string',
            'variants.*.content' => 'required|string',
        ]);

        $experiment = Experiment::create([
            'tenant_id' => $request->user->tenant_id,
            'name' => $validated['name'],
            'subject_type' => $validated['subject_type'],
            'status' => 'draft',
        ]);

        foreach ($validated['variants'] as $variant) {
            $experiment->variants()->create($variant);
        }

        return response()->json(['data' => $experiment->load('variants')], 201);
    }

    public function start(Request $request, $id)
    {
        $experiment = Experiment::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $experiment->update(['status' => 'running', 'started_at' => now()]);
        return response()->json(['data' => $experiment]);
    }

    public function trackEvent(Request $request, $variantId)
    {
        $validated = $request->validate([
            'event' => 'required|in:view,engagement,conversion',
            'revenue' => 'nullable|numeric|min:0',
        ]);

        $variant = ExperimentVariant::whereHas('experiment', function ($q) use ($request) {
            $q->where('tenant_id', $request->user->tenant_id);
        })->findOrFail($variantId);

        match ($validated['event']) {
            'view' => $variant->increment('views'),
            'engagement' => $variant->increment('engagements'),
            'conversion' => $variant->update([
                'conversions' => $variant->conversions + 1,
                'revenue' => $variant->revenue + ($validated['revenue'] ?? 0),
            ]),
        };

        return response()->json(['data' => $variant->fresh()]);
    }

    /**
     * PHASE 13: winning variant. Deliberately conservative — requires a
     * real minimum sample size (30 views) per variant before declaring a
     * winner, otherwise reports "insufficient data" rather than guessing.
     */
    public function getResults(Request $request, $id)
    {
        $experiment = Experiment::where('tenant_id', $request->user->tenant_id)
            ->with('variants')
            ->findOrFail($id);

        $results = $experiment->variants->map(fn ($v) => [
            'variant_id' => $v->id,
            'name' => $v->name,
            'views' => $v->views,
            'engagements' => $v->engagements,
            'conversions' => $v->conversions,
            'revenue' => (float) $v->revenue,
            'conversion_rate' => $v->conversionRate(),
        ]);

        $minSampleSize = 30;
        $eligible = $experiment->variants->filter(fn ($v) => $v->views >= $minSampleSize);

        $winner = null;
        $note = null;

        if ($eligible->count() < 2) {
            $note = "Not enough data yet — each variant needs at least {$minSampleSize} views before a winner can be responsibly determined.";
        } else {
            $sorted = $eligible->sortByDesc(fn ($v) => $v->conversionRate() ?? 0)->values();
            $best = $sorted[0];
            $second = $sorted[1];

            if (($best->conversionRate() ?? 0) - ($second->conversionRate() ?? 0) < 2) {
                $note = 'No clear winner — leading variants are within 2 percentage points of each other.';
            } else {
                $winner = $best->id;
            }
        }

        if ($winner && $experiment->status === 'running') {
            $experiment->update(['winning_variant_id' => $winner, 'status' => 'completed', 'ended_at' => now()]);
        }

        return response()->json([
            'data' => $results,
            'winning_variant_id' => $winner,
            'note' => $note,
        ]);
    }
}
