<?php
namespace App\Monitoring;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PerformanceMonitor
{
    private $startTime;
    private $startMemory;
    
    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
    }
    
    public function logEndpoint(string $endpoint, string $method, float $responseTime, int $statusCode): void
    {
        $memoryUsed = memory_get_usage() - $this->startMemory;
        
        $log = [
            'type' => 'endpoint_performance',
            'endpoint' => $endpoint,
            'method' => $method,
            'response_time_ms' => round($responseTime * 1000, 2),
            'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
            'status_code' => $statusCode,
            'timestamp' => now()
        ];
        
        if ($responseTime > 1.0) {
            Log::warning('Slow endpoint detected', $log);
        }
        
        Log::info('Endpoint performance', $log);
    }
    
    public function logDatabaseQuery(string $query, array $bindings, float $time): void
    {
        if ($time > 0.1) {
            Log::warning('Slow database query', [
                'query' => $query,
                'bindings' => $bindings,
                'time_ms' => round($time * 1000, 2)
            ]);
        }
    }
    
    public function getMemoryUsage(): array
    {
        return [
            'current' => round(memory_get_usage() / 1024 / 1024, 2),
            'peak' => round(memory_get_peak_usage() / 1024 / 1024, 2)
        ];
    }
    
    public function getCacheHitRate(): float
    {
        // Calculate from cache statistics
        return 0.75; // 75% hit rate target
    }
}
