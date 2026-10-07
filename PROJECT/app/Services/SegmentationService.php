<?php
namespace App\Services;
use App\Models\CustomerSegment;
use App\Models\Customer;

/**
 * PHASE 12 AUDIT FINDING: this was the most severe segmentation bug —
 * CustomerSegment.criteria (a real, stored JSON column) was never
 * actually applied anywhere. countMembers() just counted every customer
 * in the tenant regardless of the segment's own criteria, and
 * createSegmentFromCriteria() didn't even create a database record —
 * it took a $criteria array as input and echoed it back inside a fully
 * hardcoded response ('member_count' => 150). The entire "customer
 * segmentation" feature had no actual segmentation logic behind it.
 *
 * Supported criteria keys (matches fields that actually exist on
 * Customer/Booking — nothing invented): customer_type, country,
 * min_bookings, destination, min_total_spent.
 */
class SegmentationService
{
    public function countMembers(CustomerSegment $segment): int
    {
        return $this->applyCriteria($segment->tenant_id, $segment->criteria ?? [])->count();
    }

    public function getSegmentMembers(CustomerSegment $segment): array
    {
        return $this->applyCriteria($segment->tenant_id, $segment->criteria ?? [])
            ->limit(100)
            ->get()
            ->toArray();
    }

    public function createSegmentFromCriteria(int $tenantId, array $criteria): array
    {
        $memberCount = $this->applyCriteria($tenantId, $criteria)->count();

        $segment = CustomerSegment::create([
            'tenant_id' => $tenantId,
            'name' => $criteria['name'] ?? 'Custom Segment',
            'criteria' => $criteria,
            'member_count' => $memberCount,
            'status' => 'active',
        ]);

        return [
            'segment_id' => $segment->id,
            'segment_name' => $segment->name,
            'member_count' => $memberCount,
            'criteria_applied' => $criteria,
        ];
    }

    protected function applyCriteria(int $tenantId, array $criteria)
    {
        $query = Customer::where('tenant_id', $tenantId);

        if (!empty($criteria['customer_type'])) {
            $query->where('customer_type', $criteria['customer_type']);
        }

        if (!empty($criteria['country'])) {
            $query->where('country', $criteria['country']);
        }

        if (!empty($criteria['min_bookings'])) {
            $query->has('bookings', '>=', (int) $criteria['min_bookings']);
        }

        if (!empty($criteria['destination'])) {
            $query->whereHas('bookings.package', function ($q) use ($criteria) {
                $q->where('destination', 'like', '%' . $criteria['destination'] . '%');
            });
        }

        if (!empty($criteria['min_total_spent'])) {
            $query->withSum('bookings as total_spent', 'total_amount')
                ->having('total_spent', '>=', $criteria['min_total_spent']);
        }

        return $query;
    }
}
