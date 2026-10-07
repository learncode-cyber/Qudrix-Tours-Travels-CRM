<?php

namespace App\Http\Controllers;

use App\Models\SalesScript;
use App\Models\ObjectionResponse;
use App\Models\SalesStrategyConfig;
use App\Models\Lead;
use App\Models\Conversation;
use App\Services\AI\AICopilotService;
use Illuminate\Http\Request;

class SalesCopilotController extends Controller
{
    public function __construct(protected AICopilotService $copilot)
    {
    }

    public function getStrategy(Request $request)
    {
        $config = SalesStrategyConfig::where('tenant_id', $request->user->tenant_id)->first();
        return response()->json(['data' => ['strategy' => $config->strategy ?? 'consultative']]);
    }

    public function setStrategy(Request $request)
    {
        $validated = $request->validate([
            'strategy' => 'required|in:consultative,spin,solution,value,relationship,challenger,sandler',
        ]);

        $config = SalesStrategyConfig::updateOrCreate(
            ['tenant_id' => $request->user->tenant_id],
            ['strategy' => $validated['strategy']]
        );

        return response()->json(['data' => $config]);
    }

    public function indexScripts(Request $request)
    {
        $query = SalesScript::where('tenant_id', $request->user->tenant_id);
        if ($request->category) $query->where('category', $request->category);
        if ($request->strategy) $query->where('strategy', $request->strategy);

        return response()->json(['data' => $query->get()]);
    }

    public function storeScript(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|in:opening,discovery,closing,follow_up',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'strategy' => 'nullable|in:consultative,spin,solution,value,relationship,challenger,sandler',
        ]);

        $script = SalesScript::create(['tenant_id' => $request->user->tenant_id, ...$validated]);
        return response()->json(['data' => $script], 201);
    }

    public function deleteScript(Request $request, $id)
    {
        SalesScript::where('tenant_id', $request->user->tenant_id)->findOrFail($id)->delete();
        return response()->json(['message' => 'Script deleted']);
    }

    public function indexObjections(Request $request)
    {
        return response()->json(['data' => ObjectionResponse::where('tenant_id', $request->user->tenant_id)->get()]);
    }

    public function storeObjection(Request $request)
    {
        $validated = $request->validate([
            'objection' => 'required|string|max:255',
            'suggested_response' => 'required|string',
        ]);

        $objection = ObjectionResponse::create(['tenant_id' => $request->user->tenant_id, ...$validated]);
        return response()->json(['data' => $objection], 201);
    }

    public function deleteObjection(Request $request, $id)
    {
        ObjectionResponse::where('tenant_id', $request->user->tenant_id)->findOrFail($id)->delete();
        return response()->json(['message' => 'Objection response deleted']);
    }

    public function dealRisk(Request $request, $leadId)
    {
        $lead = Lead::where('tenant_id', $request->user->tenant_id)->findOrFail($leadId);
        return response()->json(['data' => $this->copilot->dealRiskScore($lead)]);
    }

    public function nextBestAction(Request $request, $leadId)
    {
        $lead = Lead::where('tenant_id', $request->user->tenant_id)->findOrFail($leadId);
        return response()->json(['data' => $this->copilot->nextBestAction($lead)]);
    }

    public function pipelineRisk(Request $request)
    {
        $leads = Lead::where('tenant_id', $request->user->tenant_id)
            ->whereNotIn('status', ['won', 'lost'])
            ->get();

        $scored = $leads->map(fn ($lead) => $this->copilot->dealRiskScore($lead))
            ->sortByDesc('risk_score')
            ->values();

        return response()->json(['data' => $scored]);
    }

    public function suggestReply(Request $request, $conversationId)
    {
        $validated = $request->validate(['message' => 'required|string']);

        $conversation = Conversation::where('tenant_id', $request->user->tenant_id)->findOrFail($conversationId);
        $result = $this->copilot->suggestReply($conversation, $validated['message']);

        return response()->json(['data' => $result]);
    }
}
