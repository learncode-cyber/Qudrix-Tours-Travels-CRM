<?php
namespace App\Services;
use App\Models\Automation;
use App\Models\AutomationTemplate;
use App\Models\AutomationStep;

class AutomationService
{
    public function createFromTemplate(int $tenantId, int $templateId, array $customData): Automation
    {
        $template = AutomationTemplate::where('tenant_id', $tenantId)->findOrFail($templateId);
        $automation = Automation::create([
            'tenant_id' => $tenantId,
            'name' => $customData['name'] ?? $template->name,
            'trigger_type' => $template->workflow_config['trigger_type'] ?? 'webhook',
            'status' => 'draft'
        ]);
        
        foreach ($template->workflow_config['steps'] ?? [] as $index => $stepConfig) {
            AutomationStep::create([
                'automation_id' => $automation->id,
                'step_order' => $index + 1,
                'action_type' => $stepConfig['action_type'],
                'action_config' => $stepConfig['action_config'],
                'condition_type' => $stepConfig['condition_type'] ?? null,
                'condition_config' => $stepConfig['condition_config'] ?? null,
            ]);
        }
        
        return $automation;
    }
    
    public function duplicateAutomation(Automation $automation): Automation
    {
        $newAutomation = $automation->replicate();
        $newAutomation->name = $automation->name . ' (Copy)';
        $newAutomation->status = 'draft';
        $newAutomation->save();
        
        foreach ($automation->steps as $step) {
            $step->replicate()->setAttribute('automation_id', $newAutomation->id)->save();
        }
        
        return $newAutomation;
    }
    
    public function getAutomationsByTrigger(int $tenantId, string $triggerType): array
    {
        return Automation::where('tenant_id', $tenantId)
            ->where('trigger_type', $triggerType)
            ->where('is_active', true)
            ->get()
            ->toArray();
    }
}
