<?php
namespace App\Services;
use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\AutomationLog;
use App\Models\Customer;
use App\Models\Task;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AutomationEngine
{
    public function __construct(private NotificationService $notifications)
    {
    }

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
        $context['tenant_id'] = $automation->tenant_id;
        $steps = $automation->steps()->orderBy('step_order')->get();
        $results = [];

        foreach ($steps as $step) {
            if (!$this->checkCondition($step, $context)) continue;
            // NOTE: a synchronous sleep() blocks the whole request for
            // delay_seconds. Fine for short delays invoked via a queued
            // job; do not call this from a request thread with a large
            // delay configured. Queued dispatch of automation execution
            // is a Production Hardening (Phase 15) concern, not fixed here.
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
        if (empty($config['to'])) {
            return ['type' => 'email', 'sent' => false, 'reason' => 'No "to" address configured on this step'];
        }
        try {
            Mail::raw($config['message'] ?? '', function ($mail) use ($config) {
                $mail->to($config['to'])->subject($config['subject'] ?? '(no subject)');
            });
            return ['type' => 'email', 'recipient' => $config['to'], 'subject' => $config['subject'] ?? null, 'sent' => true];
        } catch (\Throwable $e) {
            Log::warning('Automation send_email step failed', ['error' => $e->getMessage()]);
            return ['type' => 'email', 'recipient' => $config['to'], 'sent' => false, 'reason' => $e->getMessage()];
        }
    }

    protected function sendSms(array $config, array $context): array
    {
        // No SMS provider is configured anywhere in this codebase and none
        // was specified in the project's requirements — per the directive,
        // an integration without a supplied API contract is not invented.
        return [
            'type' => 'sms',
            'recipient' => $config['phone'] ?? null,
            'sent' => false,
            'reason' => 'CONTRACT REQUIRED: no SMS provider is configured (see Integration Manager, Directive §18)',
        ];
    }

    protected function createTask(array $config, array $context): array
    {
        if (empty($config['title'])) {
            return ['type' => 'task', 'created' => false, 'reason' => 'No "title" configured on this step'];
        }
        $task = Task::create([
            'tenant_id' => $context['tenant_id'],
            'assigned_to' => $config['assignee'] ?? null,
            'title' => $config['title'],
            'description' => $config['description'] ?? null,
            'type' => 'automation',
            'status' => 'open',
            'priority' => $config['priority'] ?? 'normal',
            'related_entity_type' => $config['related_entity_type'] ?? null,
            'related_entity_id' => $config['related_entity_id'] ?? null,
            'due_date' => $config['due_date'] ?? null,
        ]);
        return ['type' => 'task', 'task_id' => $task->id, 'created' => true];
    }

    protected function updateCustomer(array $config, array $context): array
    {
        $customerId = $config['customer_id'] ?? $context['customer_id'] ?? null;
        if (!$customerId || empty($config['field'])) {
            return ['type' => 'customer_update', 'updated' => false, 'reason' => 'customer_id and field are required'];
        }
        $customer = Customer::where('tenant_id', $context['tenant_id'])->find($customerId);
        if (!$customer) {
            return ['type' => 'customer_update', 'updated' => false, 'reason' => "Customer #{$customerId} not found in this tenant"];
        }
        if (!in_array($config['field'], $customer->getFillable(), true)) {
            return ['type' => 'customer_update', 'updated' => false, 'reason' => "Field '{$config['field']}' is not updatable"];
        }
        $customer->update([$config['field'] => $config['value'] ?? null]);
        return ['type' => 'customer_update', 'customer_id' => $customer->id, 'field' => $config['field'], 'updated' => true];
    }

    protected function createNotification(array $config, array $context): array
    {
        if (empty($config['user_id']) || empty($config['message'])) {
            return ['type' => 'notification', 'created' => false, 'reason' => 'user_id and message are required'];
        }
        $result = $this->notifications->send(
            $context['tenant_id'],
            $config['user_id'],
            $config['notification_type'] ?? 'automation',
            $config['title'] ?? 'Automation notification',
            $config['message'],
            $context,
            $config['channels'] ?? ['in_app']
        );
        return ['type' => 'notification', 'notification_id' => $result['notification']->id, 'delivery' => $result['delivery'], 'created' => true];
    }

    protected function callWebhook(array $config, array $context): array
    {
        if (empty($config['url'])) {
            return ['type' => 'webhook', 'status' => 'error', 'reason' => 'No "url" configured on this step'];
        }
        $url = $config['url'];
        $payload = $config['payload'] ?? $context;
        try {
            $response = Http::timeout(10)->post($url, $payload);
            return ['type' => 'webhook', 'url' => $url, 'status_code' => $response->status(), 'status' => $response->successful() ? 'delivered' : 'failed'];
        } catch (\Throwable $e) {
            Log::warning('Automation webhook step failed', ['url' => $url, 'error' => $e->getMessage()]);
            return ['type' => 'webhook', 'url' => $url, 'status' => 'error', 'reason' => $e->getMessage()];
        }
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
