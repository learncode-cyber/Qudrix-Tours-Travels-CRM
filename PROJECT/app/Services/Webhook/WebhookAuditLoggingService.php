<?php

namespace App\Services\Webhook;

use App\Models\User;
use App\Models\Webhook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class WebhookAuditLoggingService
{
    /**
     * Log webhook action
     */
    public function logWebhookAction(string $action, Webhook $webhook, ?array $changes = null, ?string $reason = null): void
    {
        DB::table('webhook_audit_logs')->insert([
            'webhook_id' => $webhook->id,
            'user_id' => Auth::id(),
            'action' => $action,
            'changes' => $changes ? json_encode($changes) : null,
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Log webhook delivery attempt
     */
    public function logDeliveryAttempt(Webhook $webhook, string $eventType, array $payload, array $result): void
    {
        DB::table('webhook_delivery_audit_logs')->insert([
            'webhook_id' => $webhook->id,
            'event_type' => $eventType,
            'request_payload' => json_encode($payload),
            'response_status' => $result['status'] ?? null,
            'response_body' => $result['body'] ?? null,
            'delivery_time_ms' => $result['time_ms'] ?? null,
            'success' => $result['success'] ?? false,
            'retry_count' => $result['retry_count'] ?? 0,
            'created_at' => now(),
        ]);
    }

    /**
     * Log security event
     */
    public function logSecurityEvent(string $eventType, string $severity, array $details): void
    {
        DB::table('webhook_security_audit_logs')->insert([
            'event_type' => $eventType,
            'severity' => $severity,
            'user_id' => Auth::id(),
            'ip_address' => request()->ip(),
            'details' => json_encode($details),
            'created_at' => now(),
        ]);
    }

    /**
     * Get webhook audit trail
     */
    public function getWebhookAuditTrail(Webhook $webhook, int $limit = 100): array
    {
        $logs = DB::table('webhook_audit_logs')
            ->where('webhook_id', $webhook->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user' => $this->getUserInfo($log->user_id),
                    'changes' => $log->changes ? json_decode($log->changes, true) : null,
                    'reason' => $log->reason,
                    'ip_address' => $log->ip_address,
                    'timestamp' => $log->created_at,
                ];
            })
            ->toArray();

        return [
            'webhook_id' => $webhook->id,
            'total_logs' => DB::table('webhook_audit_logs')
                ->where('webhook_id', $webhook->id)
                ->count(),
            'logs' => $logs,
        ];
    }

    /**
     * Get delivery audit trail
     */
    public function getDeliveryAuditTrail(Webhook $webhook, int $limit = 50): array
    {
        $logs = DB::table('webhook_delivery_audit_logs')
            ->where('webhook_id', $webhook->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                return [
                    'event_type' => $log->event_type,
                    'success' => (bool)$log->success,
                    'response_status' => $log->response_status,
                    'delivery_time_ms' => $log->delivery_time_ms,
                    'retry_count' => $log->retry_count,
                    'timestamp' => $log->created_at,
                ];
            })
            ->toArray();

        return [
            'webhook_id' => $webhook->id,
            'total_deliveries_logged' => DB::table('webhook_delivery_audit_logs')
                ->where('webhook_id', $webhook->id)
                ->count(),
            'deliveries' => $logs,
        ];
    }

    /**
     * Get security audit log
     */
    public function getSecurityAuditLog(int $limit = 100): array
    {
        $logs = DB::table('webhook_security_audit_logs')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                return [
                    'event_type' => $log->event_type,
                    'severity' => $log->severity,
                    'user' => $this->getUserInfo($log->user_id),
                    'ip_address' => $log->ip_address,
                    'details' => $log->details ? json_decode($log->details, true) : null,
                    'timestamp' => $log->created_at,
                ];
            })
            ->toArray();

        return [
            'total_events' => DB::table('webhook_security_audit_logs')->count(),
            'critical_events' => DB::table('webhook_security_audit_logs')
                ->where('severity', 'critical')
                ->count(),
            'events' => $logs,
        ];
    }

    /**
     * Generate audit compliance report
     */
    public function generateComplianceReport(int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        return [
            'period' => [
                'start' => $startDate->toIso8601String(),
                'end' => $endDate->toIso8601String(),
            ],
            'summary' => [
                'total_webhook_actions' => DB::table('webhook_audit_logs')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),
                'total_deliveries' => DB::table('webhook_delivery_audit_logs')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),
                'total_security_events' => DB::table('webhook_security_audit_logs')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),
            ],
            'security_summary' => $this->getSecuritySummary($startDate, $endDate),
            'user_activity' => $this->getUserActivitySummary($startDate, $endDate),
            'deliveries_summary' => $this->getDeliveriesSummary($startDate, $endDate),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get security summary
     */
    protected function getSecuritySummary($startDate, $endDate): array
    {
        return DB::table('webhook_security_audit_logs')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('severity')
            ->selectRaw('severity, COUNT(*) as count')
            ->get()
            ->mapWithKeys(fn($item) => [$item->severity => $item->count])
            ->toArray();
    }

    /**
     * Get user activity summary
     */
    protected function getUserActivitySummary($startDate, $endDate): array
    {
        return DB::table('webhook_audit_logs')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('user_id', 'action')
            ->selectRaw('user_id, action, COUNT(*) as count')
            ->get()
            ->groupBy('user_id')
            ->map(function ($actions) {
                return $actions->groupBy('action')
                    ->map(fn($items) => $items->sum('count'))
                    ->toArray();
            })
            ->toArray();
    }

    /**
     * Get deliveries summary
     */
    protected function getDeliveriesSummary($startDate, $endDate): array
    {
        $total = DB::table('webhook_delivery_audit_logs')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $successful = DB::table('webhook_delivery_audit_logs')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('success', true)
            ->count();

        return [
            'total' => $total,
            'successful' => $successful,
            'failed' => $total - $successful,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Export audit log
     */
    public function exportAuditLog(string $type = 'webhooks', string $format = 'json'): string
    {
        $data = match ($type) {
            'webhooks' => DB::table('webhook_audit_logs')->get(),
            'deliveries' => DB::table('webhook_delivery_audit_logs')->get(),
            'security' => DB::table('webhook_security_audit_logs')->get(),
            default => collect(),
        };

        return $format === 'json' ? json_encode($data) : $this->convertToCsv($data);
    }

    /**
     * Convert data to CSV
     */
    protected function convertToCsv($data): string
    {
        if ($data->isEmpty()) {
            return '';
        }

        $headers = array_keys((array)$data->first());
        $csv = implode(',', $headers) . "\n";

        foreach ($data as $row) {
            $values = array_map(function ($value) {
                if (is_array($value) || is_object($value)) {
                    return '"' . str_replace('"', '""', json_encode($value)) . '"';
                }
                return '"' . str_replace('"', '""', (string)$value) . '"';
            }, (array)$row);

            $csv .= implode(',', $values) . "\n";
        }

        return $csv;
    }

    /**
     * Get user info
     */
    protected function getUserInfo(?int $userId): ?array
    {
        if (!$userId) {
            return null;
        }

        $user = User::find($userId);
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    /**
     * Purge old audit logs
     */
    public function purgeOldLogs(int $days = 90): void
    {
        $cutoffDate = now()->subDays($days);

        DB::table('webhook_audit_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        DB::table('webhook_delivery_audit_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        DB::table('webhook_security_audit_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
    }
}
