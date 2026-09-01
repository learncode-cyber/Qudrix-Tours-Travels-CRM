<?php
namespace App\Services;
use App\Models\HajjPackage;
use App\Models\Booking;

class HajjService
{
    public function createPackage($tenantId, array $data)
    {
        return HajjPackage::create(['tenant_id' => $tenantId, ...$data]);
    }
    
    public function getAvailablePackages($tenantId)
    {
        return HajjPackage::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->get();
    }
    
    public function updatePackageCapacity($packageId, $boost)
    {
        $pkg = HajjPackage::findOrFail($packageId);
        $pkg->max_capacity += $boost;
        $pkg->save();
        return $pkg;
    }
}
