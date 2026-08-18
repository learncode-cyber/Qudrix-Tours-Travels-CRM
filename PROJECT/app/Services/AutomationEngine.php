<?php
namespace App\Services;
use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\AutomationLog;
use Carbon\Carbon;

class AutomationEngine
{
    public function execute(Automation $automation, array $triggerData = [])
    {
        $startTime = microtime(true);
        $log = AutomationLog::create([
            'automation_id' => $automation->id,
            'trigger_data' => $triggerData,
            'status' => 'running',
            'started_at' => now()
        ]);
        
        try {
            $result = $this->executeSteps($automation, $triggerData);
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $log->update([
                'status' => 'success',
                'result_data' => $result,
                'execution_time_ms' => $executionTime,
                'completed_at' => now()
            ]);
            $automation->update(['run_count' => $automation->run_count + 1, 'last_run_at' => now()]);
            return $result;
        } catch (\Exception $e) {
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $log->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'execution_time_ms' => $executionTime,
                'completed_at' => now()
            ]);
            throw $e;
        }
    }
    
    public function executeSteps(Automation $automation, array $context = []): array
    {
        $steps = $automation->steps()->orderBy('step_order')->get();
        $results = [];
        
        foreach ($steps as $step) {
            if (!$this->checkCondition($step, $context)) continue;
            if ($step->delay_seconds > 0) sleep($step->delay_seconds);
            
            $actionResult = $this->executeAction($step->action_type, $step->action_config, $context);
            $results[$step->step_order] = $actionResult;
            $context['last_result'] = $actionResult;
        }
        
        return $results;
    }
    
    protected function checkCondition(AutomationStep $step, array $context): bool
    {
        if (!$step->condition_type) return true;
        
        $config = $step->condition_config;
        $value = $context[$config['field']] ?? null;
        
        return match($config['operator']) {
            'equals' => $value == $config['value'],
            'not_equals' => $value != $config['value'],
            'contains' => strpos((string)$value, (string)$config['value']) !== false,
            'greater_than' => (int)$value > (int)$config['value'],
            'less_than' => (int)$value < (int)$config['value'],
            default => true
        };
    }
    
    protected function executeAction(string $actionType, array $config, array $context): array
    {
        return match($actionType) {
            'send_email' => $this->sendEmail($config, $context),
            'send_sms' => $this->sendSms($config, $context),
            'create_task' => $this->createTask($config, $context),
            'update_customer' => $this->updateCustomer($config, $context),
            'create_notification' => $this->createNotification($config, $context),
            'webhook' => $this->callWebhook($config, $context),
            'delay' => ['delayed' => true],
            default => ['error' => 'Unknown action type']
        };
    }
    
    protected function sendEmail(array $config, array $context): array
    {
        return ['type' => 'email', 'recipient' => $config['to'], 'subject' => $config['subject'], 'sent' => true];
    }
    
    protected function sendSms(array $config, array $context): array
    {
        return ['type' => 'sms', 'recipient' => $config['phone'], 'message' => $config['message'], 'sent' => true];
    }
    
    protected function createTask(array $config, array $context): array
    {
        return ['type' => 'task', 'title' => $config['title'], 'assignee' => $config['assignee'], 'created' => true];
    }
    
    protected function updateCustomer(array $config, array $context): array
    {
        return ['type' => 'customer_update', 'field' => $config['field'], 'value' => $config['value'], 'updated' => true];
    }
    
    protected function createNotification(array $config, array $context): array
    {
        return ['type' => 'notification', 'message' => $config['message'], 'created' => true];
    }
    
    protected function callWebhook(array $config, array $context): array
    {
        $url = $config['url'];
        $payload = $config['payload'] ?? $context;
        return ['type' => 'webhook', 'url' => $url, 'status' => 'called'];
    }
    
    public function test(Automation $automation, array $testData = []): array
    {
        return [
            'automation_id' => $automation->id,
            'test_data' => $testData,
            'steps_count' => $automation->steps()->count(),
            'estimated_execution' => 'preview_mode',
            'valid' => $automation->steps()->count() > 0
        ];
    }
    
    public function schedule(Automation $automation, string $cronExpression): void
    {
        // Scheduling would be handled by Laravel's task scheduler
    }
}
