<?php
namespace App\Http\Controllers;

use App\Models\ConversionEvent;
use App\Services\ConversionTrackingService;
use Illuminate\Http\Request;

class ConversionEventController extends Controller
{
    public function __construct(protected ConversionTrackingService $tracking)
    {
    }

    public function index(Request $request)
    {
        $events = ConversionEvent::where('tenant_id', $request->user->tenant_id)
            ->when($request->event_name, fn ($q) => $q->where('event_name', $request->event_name))
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['data' => $events->items(), 'pagination' => [
            'total' => $events->total(),
            'per_page' => $events->perPage(),
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
        ]]);
    }

    /**
     * Records a funnel event and attempts to forward it to Meta CAPI/GA4
     * if the tenant has configured credentials. See ConversionTrackingService
     * — without real credentials this always resolves to 'not_configured',
     * not a fabricated 'sent'.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id' => 'nullable|exists:leads,id',
            'customer_id' => 'nullable|exists:customers,id',
            'event_name' => 'required|in:qualified_lead,application_started,payment,conversion',
            'event_value' => 'nullable|numeric',
            'currency' => 'nullable|string|size:3',
        ]);

        $event = ConversionEvent::create(['tenant_id' => $request->user->tenant_id, ...$validated]);
        $event = $this->tracking->send($event);

        return response()->json(['data' => $event], 201);
    }
}
