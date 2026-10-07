<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Communication;
use App\Models\CustomerFamily;

class CustomerService
{
    public function getCustomer360(int $tenantId, int $customerId)
    {
        $customer = Customer::where('tenant_id', $tenantId)->findOrFail($customerId);

        return [
            'customer' => $customer,
            'summary' => [
                'total_bookings' => $customer->bookings()->count(),
                'total_spent' => $customer->bookings()->sum('total_amount'),
                'communications' => $customer->communications()->count(),
                'family_members' => $customer->family()->count(),
                'last_contact' => $customer->communications()->latest()->first()?->created_at,
                'status' => $customer->status,
            ],
            'recent_communications' => $customer->getRecentCommunications(10),
            'family' => $customer->family,
            'upcoming_bookings' => $customer->bookings()
                ->where('travel_date', '>=', now())
                ->orderBy('travel_date')
                ->get(),
            'past_bookings' => $customer->bookings()
                ->where('travel_date', '<', now())
                ->latest()
                ->take(5)
                ->get(),
        ];
    }

    public function addFamilyMember(int $tenantId, int $customerId, array $data)
    {
        return CustomerFamily::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            ...$data
        ]);
    }

    public function searchCustomers(int $tenantId, string $query, array $filters = [])
    {
        $customers = Customer::where('tenant_id', $tenantId);

        if ($query) {
            $customers->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%");
            });
        }

        if (isset($filters['status'])) {
            $customers->where('status', $filters['status']);
        }

        if (isset($filters['country'])) {
            $customers->where('country', $filters['country']);
        }

        if (isset($filters['type'])) {
            $customers->where('customer_type', $filters['type']);
        }

        return $customers->paginate($filters['per_page'] ?? 20);
    }
}
