<?php
namespace App\Services;
use App\Models\OfflineSync;
use App\Models\SyncQueue;
use Carbon\Carbon;

class SyncService
{
    public function processSync(int $tenantId, int $userId, array $changes, ?string $batchId = null): array
    {
        $batchId = $batchId ?? uniqid('batch_');
        $synced = 0;
        $failed = 0;
        
        foreach ($changes as $change) {
            try {
                OfflineSync::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'entity_type' => $change['entity_type'] ?? 'unknown',
                    'entity_id' => $change['entity_id'] ?? null,
                    'operation' => $change['operation'] ?? 'update',
                    'payload' => $change['payload'] ?? [],
                    'status' => 'synced',
                    'synced_at' => now()
                ]);
                $synced++;
            } catch (\Exception $e) {
                $failed++;
            }
        }
        
        return ['batch_id' => $batchId, 'synced' => $synced, 'failed' => $failed, 'timestamp' => now()];
    }
    
    public function getBatchStatus(int $tenantId, string $batchId): array
    {
        $syncs = OfflineSync::where('tenant_id', $tenantId)->get();
        return [
            'batch_id' => $batchId,
            'total' => count($syncs),
            'synced' => $syncs->where('status', 'synced')->count(),
            'failed' => $syncs->where('status', 'failed')->count(),
            'pending' => $syncs->where('status', 'pending')->count()
        ];
    }
    
    public function retryFailed(int $tenantId, int $userId): array
    {
        $failed = OfflineSync::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'failed')
            ->get();
        
        $retried = 0;
        foreach ($failed as $sync) {
            $sync->update(['status' => 'synced', 'synced_at' => now()]);
            $retried++;
        }
        
        return ['retried' => $retried, 'timestamp' => now()];
    }
    
    public function queueSync(int $tenantId, int $userId, array $data): SyncQueue
    {
        return SyncQueue::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'batch_id' => uniqid('batch_'),
            'data' => $data,
            'status' => 'pending',
            'retry_count' => 0,
            'queued_at' => now()
        ]);
    }
}
