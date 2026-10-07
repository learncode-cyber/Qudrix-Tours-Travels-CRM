<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditService
{
    public function log(User $user, string $action, string $entityType, ?int $entityId, ?array $oldValues = null, ?array $newValues = null, ?string $description = null)
    {
        return AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'description' => $description ?? "{$action} on {$entityType}::{$entityId}",
            'created_at' => now(),
        ]);
    }

    public function getLogsForEntity(string $entityType, int $entityId, $limit = 50)
    {
        return AuditLog::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function getLogsForUser(User $user, $limit = 50)
    {
        return AuditLog::where('user_id', $user->id)
            ->where('tenant_id', $user->tenant_id)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function getLogsForTenant($tenantId, $limit = 100)
    {
        return AuditLog::where('tenant_id', $tenantId)
            ->latest()
            ->limit($limit)
            ->get();
    }
}
