<?php
namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\AutomationStep;
use Illuminate\Http\Request;

/**
 * MASTER_PROJECT_AUDIT.md backlog item (found 2026-09-21 building the
 * Automation frontend): routes/api.php had create/execute/test for
 * Automation itself, but no route anywhere to add, reorder, or remove the
 * AutomationStep rows that actually define what an automation does.
 * AutomationEngine::execute() would run against zero steps for every
 * automation ever created through the API — the feature was reachable but
 * functionally inert. This controller closes that gap.
 */
class AutomationStepController extends Controller
{
    public function store(Request $request, $automationId)
    {
        $automation = Automation::where('tenant_id', $request->user->tenant_id)->findOrFail($automationId);

        $validated = $request->validate([
            'step_order' => 'required|integer|min:1',
            'action_type' => 'required|string',
            'action_config' => 'nullable|array',
            'condition_type' => 'nullable|string',
            'condition_config' => 'nullable|array',
            'delay_seconds' => 'nullable|integer|min:0',
        ]);

        $step = AutomationStep::create(['automation_id' => $automation->id, ...$validated]);

        return response()->json(['data' => $step], 201);
    }

    public function update(Request $request, $automationId, $stepId)
    {
        $automation = Automation::where('tenant_id', $request->user->tenant_id)->findOrFail($automationId);
        $step = AutomationStep::where('automation_id', $automation->id)->findOrFail($stepId);

        $validated = $request->validate([
            'step_order' => 'sometimes|integer|min:1',
            'action_type' => 'sometimes|string',
            'action_config' => 'nullable|array',
            'condition_type' => 'nullable|string',
            'condition_config' => 'nullable|array',
            'delay_seconds' => 'nullable|integer|min:0',
        ]);

        $step->update($validated);

        return response()->json(['data' => $step]);
    }

    public function destroy(Request $request, $automationId, $stepId)
    {
        $automation = Automation::where('tenant_id', $request->user->tenant_id)->findOrFail($automationId);
        $step = AutomationStep::where('automation_id', $automation->id)->findOrFail($stepId);
        $step->delete();

        return response()->json(['message' => 'Step removed']);
    }
}
