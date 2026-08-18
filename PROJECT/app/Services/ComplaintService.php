<?php
namespace App\Services;
use App\Models\Complaint;

class ComplaintService
{
    public function resolveComplaint($complaintId, $resolution)
    {
        $complaint = Complaint::findOrFail($complaintId);
        $complaint->update(['status' => 'resolved', 'resolution' => $resolution, 'resolution_date' => now()]);
        return $complaint;
    }
    
    public function getStatsByPriority($tenantId)
    {
        return Complaint::where('tenant_id', $tenantId)
            ->groupBy('priority')
            ->selectRaw('priority, count(*) as count')
            ->pluck('count', 'priority');
    }
    
    public function getAverageResolutionTime($tenantId)
    {
        $resolved = Complaint::where('tenant_id', $tenantId)
            ->whereNotNull('resolution_date')
            ->selectRaw('DATEDIFF(resolution_date, created_at) as days_to_resolve')
            ->get();
        return $resolved->avg('days_to_resolve');
    }
}
