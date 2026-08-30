<?php
namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\Ai\AiProviderException;
use App\Services\Ai\AiSalesAgentService;
use Illuminate\Http\Request;

class AiSalesAgentController extends Controller
{
    public function __construct(private AiSalesAgentService $agent)
    {
    }

    public function qualifyLead(Request $request, $leadId)
    {
        $lead = Lead::where('tenant_id', $request->user->tenant_id)->findOrFail($leadId);

        try {
            return response()->json(['data' => $this->agent->qualifyLead($lead, $request->user->id)]);
        } catch (AiProviderException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    public function summarize(Request $request, $leadId)
    {
        $lead = Lead::where('tenant_id', $request->user->tenant_id)->findOrFail($leadId);

        try {
            return response()->json(['data' => $this->agent->summarizeConversation($lead, $request->user->id)]);
        } catch (AiProviderException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    public function suggestReply(Request $request, $leadId)
    {
        $lead = Lead::where('tenant_id', $request->user->tenant_id)->findOrFail($leadId);
        $validated = $request->validate(['rep_intent' => 'nullable|string|max:2000']);

        try {
            return response()->json([
                'data' => $this->agent->suggestReply($lead, $validated['rep_intent'] ?? null, $request->user->id),
            ]);
        } catch (AiProviderException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }
}
