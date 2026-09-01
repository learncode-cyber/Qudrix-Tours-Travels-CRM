<?php
namespace App\Services;
use App\Models\OfflineData;
use App\Models\Booking;
use App\Models\Customer;

class OfflineDataService
{
    public function prepareOfflineData(int $tenantId, string $dataType = 'all'): array
    {
        $data = [];
        
        if ($dataType === 'all' || $dataType === 'bookings') {
            $data['bookings'] = Booking::where('tenant_id', $tenantId)->limit(1000)->get()->toArray();
        }
        if ($dataType === 'all' || $dataType === 'customers') {
            $data['customers'] = Customer::where('tenant_id', $tenantId)->limit(500)->get()->toArray();
        }
        
        return $data;
    }
    
    public function getOfflineStatus(int $tenantId, int $userId): array
    {
        $data = OfflineData::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->get();
        
        $totalSize = $data->sum('size_kb');
        
        return [
            'total_items' => count($data),
            'total_size_mb' => round($totalSize / 1024, 2),
            'by_type' => $data->groupBy('data_type')->map->count()->toArray(),
            'last_synced' => $data->max('last_synced')
        ];
    }
    
    public function syncOfflineChanges(int $tenantId, int $userId, array $changes): array
    {
        $synced = 0;
        foreach ($changes as $change) {
            try {
                OfflineData::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'data_type' => $change['type'] ?? 'unknown',
                    'data' => $change['data'] ?? [],
                    'size_kb' => strlen(json_encode($change['data'])) / 1024,
                    'sync_status' => 'synced',
                    'last_synced' => now()
                ]);
                $synced++;
            } catch (\Exception $e) {
                // Log error
            }
        }
        return ['synced' => $synced, 'timestamp' => now()];
    }
    
    public function clearOfflineData(int $tenantId, int $userId): void
    {
        OfflineData::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->delete();
    }
}
