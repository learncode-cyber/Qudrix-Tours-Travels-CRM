<?php
namespace App\Http\Controllers;

use App\Services\BehavioralAnalyticsService;
use Illuminate\Http\Request;

// Executive dashboard + behavioural analytics, all computed from real rows
// (Directive S3.A, S12, S27).
class AnalyticsDashboardController extends Controller
{
    public function __construct(private BehavioralAnalyticsService $analytics)
    {
    }

    public function executive(Request $request)
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        return response()->json(['data' => $this->analytics->executiveDashboard(
            $request->user->tenant_id,
            isset($validated['from']) ? now()->parse($validated['from']) : null,
            isset($validated['to']) ? now()->parse($validated['to']) : null,
        )]);
    }

    public function behavioral(Request $request)
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        return response()->json(['data' => $this->analytics->behavioralMetrics(
            $request->user->tenant_id,
            isset($validated['from']) ? now()->parse($validated['from']) : null,
            isset($validated['to']) ? now()->parse($validated['to']) : null,
        )]);
    }

    public function pipeline(Request $request)
    {
        return response()->json(['data' => $this->analytics->salesPipeline($request->user->tenant_id)]);
    }

    public function revenueTrend(Request $request)
    {
        $validated = $request->validate(['months' => 'nullable|integer|min:1|max:36']);

        return response()->json(['data' => $this->analytics->revenueTrend(
            $request->user->tenant_id,
            $validated['months'] ?? 6,
        )]);
    }

    public function quotationFunnel(Request $request)
    {
        return response()->json(['data' => $this->analytics->quotationFunnel(
            $request->user->tenant_id,
            $request->from ? now()->parse($request->from) : now()->startOfMonth(),
            $request->to ? now()->parse($request->to) : now(),
        )]);
    }
}
