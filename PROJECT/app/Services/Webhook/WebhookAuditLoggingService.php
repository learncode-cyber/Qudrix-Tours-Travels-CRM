<?php

namespace App\Services\Webhook;

use App\Models\User;
use App\Models\Webhook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * FIX (2026-09-25): every method here now threads a $tenantId through —
 * see database/migrations/2024_08_20_000001_create_webhook_audit_log_tables.php
 * for the full history (these 3 tables didn't exist at all until this fix,
 * so every read/write below was previously either crashing or, once the
 * tables existed, would have leaked every tenant's audit/security/
 * compliance data to every other tenant with zero filtering).
 */
class WebhookAuditLoggingService
{
    /**
     * Log webhook action
     */
    public function logWebhookAction(string $action, Webhook $webhook, ?array $changes = null, ?string $reason = null): void
    {
        DB::table('webhook_audit_logs')->insert([
            'tenant_id' => $webhook->tenant_id,
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
            'tenant_id' => $webhook->tenant_id,
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
     * Log security event. $tenantId is nullable — some security events
     * (e.g. an auth failure with an invalid/unrecognized API key) may not
     * be attributable to any tenant at all.
     */
    public function logSecurityEvent(string $eventType, string $severity, array $details, ?int $tenantId = null): void
    {
        DB::table('webhook_security_audit_logs')->insert([
            'tenant_id' => $tenantId,
            'event_type' => $eventType,
            'severity' => $severity,
            'user_id' => Auth::id(),
            'ip_address' => request()->ip(),
            'details' => json_encode($details),
            'created_at' => now(),
        ]);
    }

    /**
     * Get webhook audit trail. $webhook is assumed already tenant-checked
     * by the caller (AdminWebhookController@authorizeWebhook or the
     * webhook.tenant middleware) — filtering by webhook_id is enough here.
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
     * Get delivery audit trail (same tenant-check assumption as above).
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
     * Get security audit log — FIX: now requires $tenantId and filters by
     * it; previously returned every tenant's security events to anyone.
     */
    public function getSecurityAuditLog(int $tenantId, int $limit = 100): array
    {
        $logs = DB::table('webhook_security_audit_logs')
            ->where('tenant_id', $tenantId)
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
            'total_events' => DB::table('webhook_security_audit_logs')->where('tenant_id', $tenantId)->count(),
            'logs' => $logs,
        ];
    }

    /**
     * Generate audit compliance report — FIX: now requires $tenantId and
     * filters every underlying query by it, including webhook_audit_logs
     * and webhook_delivery_audit_logs (joined via webhook_id previously
     * having no tenant awareness at all — now filtered directly by the
     * tenant_id column added on all 3 tables).
     */
    public function generateComplianceReport(int $tenantId, int $days = 30): array
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
                    ->where('tenant_id', $tenantId)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),
                'total_deliveries' => DB::table('webhook_delivery_audit_logs')
                    ->where('tenant_id', $tenantId)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),
                'total_security_events' => DB::table('webhook_security_audit_logs')
                    ->where('tenant_id', $tenantId)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),
            ],
            'security_summary' => $this->getSecuritySummary($tenantId, $startDate, $endDate),
            'user_activity' => $this->getUserActivitySummary($tenantId, $startDate, $endDate),
            'deliveries_summary' => $this->getDeliveriesSummary($tenantId, $startDate, $endDate),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    protected function getSecuritySummary(int $tenantId, $startDate, $endDate): array
    {
        return DB::table('webhook_security_audit_logs')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('severity')
            ->selectRaw('severity, COUNT(*) as count')
            ->get()
            ->mapWithKeys(fn($item) => [$item->severity => $item->count])
            ->toArray();
    }

    protected function getUserActivitySummary(int $tenantId, $startDate, $endDate): array
    {
        return DB::table('webhook_audit_logs')
            ->where('tenant_id', $tenantId)
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

    protected function getDeliveriesSummary(int $tenantId, $startDate, $endDate): array
    {
        $total = DB::table('webhook_delivery_audit_logs')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $successful = DB::table('webhook_delivery_audit_logs')
            ->where('tenant_id', $tenantId)
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
     * Export audit log — FIX: now requires $tenantId and filters every
     * exported table by it.
     */
    public function exportAuditLog(int $tenantId, string $type = 'webhooks', string $format = 'json'): string
    {
        $data = match ($type) {
            'webhooks' => DB::table('webhook_audit_logs')->where('tenant_id', $tenantId)->get(),
            'deliveries' => DB::table('webhook_delivery_audit_logs')->where('tenant_id', $tenantId)->get(),
            'security' => DB::table('webhook_security_audit_logs')->where('tenant_id', $tenantId)->get(),
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
     * Purge old audit logs (maintenance-only; not currently wired to any
     * route or scheduled command, so left as a global, all-tenant purge
     * by age — a delete-only cleanup task, not a data-disclosure concern).
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
