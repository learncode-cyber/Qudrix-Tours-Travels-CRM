<?php
namespace App\Services;
use App\Models\ServiceWorkerCache;
use App\Models\CachePolicy;

class CacheService
{
    public function getCachePolicies(int $tenantId): array
    {
        $policies = CachePolicy::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->toArray();
        
        return [
            'strategies' => [
                'network_first' => 'Try network, fallback to cache',
                'cache_first' => 'Try cache, fallback to network',
                'stale_while_revalidate' => 'Serve from cache, update in background'
            ],
            'policies' => $policies
        ];
    }
    
    public function clearTenantCache(int $tenantId): int
    {
        return ServiceWorkerCache::where('tenant_id', $tenantId)->delete();
    }
    
    public function getCacheStats(int $tenantId): array
    {
        $caches = ServiceWorkerCache::where('tenant_id', $tenantId)->get();
        
        return [
            'total_items' => count($caches),
            'by_status' => [
                'active' => $caches->where('status', 'active')->count(),
                'expired' => $caches->where('status', 'expired')->count()
            ],
            'cache_names' => $caches->pluck('cache_name')->unique()->values()->toArray()
        ];
    }
    
    public function cacheResource(int $tenantId, string $cacheName, string $url, string $status = 'active'): void
    {
        ServiceWorkerCache::create([
            'tenant_id' => $tenantId,
            'cache_name' => $cacheName,
            'resource_url' => $url,
            'status' => $status,
            'cached_at' => now(),
            'expires_at' => now()->addHours(24)
        ]);
    }
}
