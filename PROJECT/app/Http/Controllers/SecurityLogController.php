<?php
namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\AuditLog;
use App\Models\FailedLoginAttempt;
use Illuminate\Http\Request;

// Read-only views over the security trail (Directive S19/S23). These are
// admin surfaces: they are mounted under the configurable admin prefix and
// scoped to the caller's tenant.
class SecurityLogController extends Controller
{
    public function accessLogs(Request $request)
    {
        $this->authorize('admin');
        $validated = $request->validate([
            'suspicious_only' => 'nullable|boolean',
            'ip' => 'nullable|string|max:45',
            'status_code' => 'nullable|integer',
            'from' => 'nullable|date',
        ]);

        $logs = AccessLog::where('tenant_id', $request->user->tenant_id)
            ->when($validated['suspicious_only'] ?? false, fn ($q) => $q->where('is_suspicious', true))
            ->when($validated['ip'] ?? null, fn ($q, $v) => $q->where('ip_address', $v))
            ->when($validated['status_code'] ?? null, fn ($q, $v) => $q->where('status_code', $v))
            ->when($validated['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->latest('created_at')
            ->paginate($request->per_page ?? 50);

        return response()->json(['data' => $logs->items(), 'total' => $logs->total()]);
    }

    public function auditLogs(Request $request)
    {
        $this->authorize('admin');
        $logs = AuditLog::where('tenant_id', $request->user->tenant_id)
            ->when($request->entity_type, fn ($q, $v) => $q->where('entity_type', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->latest('created_at')
            ->paginate($request->per_page ?? 50);

        return response()->json(['data' => $logs->items(), 'total' => $logs->total()]);
    }

    // Not tenant-scoped: a failed login happens before any tenant is known,
    // and enumeration attempts against unknown addresses have no tenant at
    // all. Access is restricted by the admin route prefix instead.
    public function failedLogins(Request $request)
    {
        $this->authorize('admin');
        $attempts = FailedLoginAttempt::when($request->email, fn ($q, $v) => $q->where('email', $v))
            ->when($request->ip_address, fn ($q, $v) => $q->where('ip_address', $v))
            ->latest('created_at')
            ->paginate($request->per_page ?? 50);

        return response()->json(['data' => $attempts->items(), 'total' => $attempts->total()]);
    }

    // Aggregated view of what is currently worth attention.
    public function summary(Request $request)
    {
        $this->authorize('admin');
        $tenantId = $request->user->tenant_id;
        $since = now()->subDay();

        return response()->json(['data' => [
            'window' => 'last 24 hours',
            'total_requests' => AccessLog::where('tenant_id', $tenantId)->where('created_at', '>=', $since)->count(),
            'suspicious_requests' => AccessLog::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $since)->where('is_suspicious', true)->count(),
            'by_reason' => AccessLog::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $since)
                ->where('is_suspicious', true)
                ->selectRaw('suspicion_reason, COUNT(*) as total')
                ->groupBy('suspicion_reason')
                ->get(),
            'top_ips_by_failure' => AccessLog::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $since)
                ->where('is_suspicious', true)
                ->selectRaw('ip_address, COUNT(*) as total')
                ->groupBy('ip_address')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'failed_logins' => FailedLoginAttempt::where('created_at', '>=', $since)->count(),
        ]]);
    }
}
