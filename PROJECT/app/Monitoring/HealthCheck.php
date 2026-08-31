<?php
namespace App\Monitoring;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthCheck
{
    public function getSystemHealth(): array
    {
        $database = $this->checkDatabase();
        $cache = $this->checkCache();
        $disk = $this->checkDiskSpace();

        return [
            'database' => $database,
            'cache' => $cache,
            'disk' => $disk,
            'memory' => $this->checkMemory(),
            // FIXED: passing the already-computed checks in, rather than
            // having getOverallStatus() call getSystemHealth() again, which
            // was infinite recursion — every call to this method would have
            // recursed until it hit PHP's function-nesting limit and fataled.
            // This had never been triggered because the routes calling it
            // required jwt.auth and were never exercised with a valid token
            // until this verification pass.
            'status' => $this->getOverallStatus($database, $disk),
        ];
    }

    private function checkDatabase(): array
    {
        $startedAt = microtime(true);
        try {
            DB::connection()->getPdo();
            // FIXED: latency_ms was a hardcoded `2`, not a measurement.
            $latencyMs = round((microtime(true) - $startedAt) * 1000, 2);
            return ['status' => 'healthy', 'latency_ms' => $latencyMs];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $probe = 'health_check_' . bin2hex(random_bytes(4));
            Cache::put($probe, 'ok', 60);
            $value = Cache::get($probe);
            Cache::forget($probe);

            if ($value !== 'ok') {
                return ['status' => 'degraded', 'error' => 'Cache did not return the value it was given'];
            }

            // FIXED: hit_rate was a hardcoded `0.75` — a fabricated metric
            // (Directive S27). This driver does not expose real hit/miss
            // counters, so the field is omitted rather than invented; only
            // a real round-trip write/read check is reported.
            return ['status' => 'healthy'];
        } catch (\Exception $e) {
            return ['status' => 'degraded', 'error' => $e->getMessage()];
        }
    }

    private function checkDiskSpace(): array
    {
        $free = disk_free_space('/');
        $total = disk_total_space('/');
        $percentUsed = ($free === false || $total === false || $total <= 0)
            ? null
            : (($total - $free) / $total) * 100;

        return [
            'free_gb' => $free !== false ? round($free / 1024 / 1024 / 1024, 2) : null,
            'total_gb' => $total !== false ? round($total / 1024 / 1024 / 1024, 2) : null,
            'percent_used' => $percentUsed !== null ? round($percentUsed, 2) : null,
            'status' => $percentUsed !== null ? ($percentUsed < 80 ? 'healthy' : 'warning') : 'unknown',
        ];
    }

    private function checkMemory(): array
    {
        $current = memory_get_usage();
        $peak = memory_get_peak_usage();
        $limit = ini_get('memory_limit');

        return [
            'current_mb' => round($current / 1024 / 1024, 2),
            'peak_mb' => round($peak / 1024 / 1024, 2),
            'limit' => $limit,
        ];
    }

    private function getOverallStatus(array $database, array $disk): string
    {
        if (($database['status'] ?? null) === 'unhealthy') {
            return 'critical';
        }
        if (($disk['status'] ?? null) === 'warning') {
            return 'degraded';
        }

        return 'healthy';
    }
}
