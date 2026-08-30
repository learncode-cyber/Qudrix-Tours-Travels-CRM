<?php
namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\Ai\AiCopilotService;
use App\Services\Ai\AiProviderException;
use Illuminate\Http\Request;

class AiCopilotController extends Controller
{
    public function __construct(private AiCopilotService $copilot)
    {
    }

    public function assist(Request $request, $leadId)
    {
        $lead = Lead::where('tenant_id', $request->user->tenant_id)->findOrFail($leadId);
        $validated = $request->validate(['latest_customer_message' => 'nullable|string|max:5000']);

        try {
            return response()->json([
                'data' => $this->copilot->assist(
                    $lead,
                    $validated['latest_customer_message'] ?? null,
                    $request->user->id,
                ),
            ]);
        } catch (AiProviderException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    // Returns candidates only — a human confirms each before it is stored
    // via POST /customer-memories.
    public function extractMemory(Request $request, $leadId)
    {
        $lead = Lead::where('tenant_id', $request->user->tenant_id)->findOrFail($leadId);

        try {
            return response()->json([
                'data' => $this->copilot->extractMemoryCandidates($lead, $request->user->id),
            ]);
        } catch (AiProviderException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }
}
