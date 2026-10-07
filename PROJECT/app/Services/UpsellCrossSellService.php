<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\UpsellOffer;
use App\Models\Package;

/**
 * PHASE 13: Upsell/Cross-sell. Deliberately deterministic and rule-based
 * — no AI call. Recommends only from real, tenant-configured UpsellOffer
 * rows and real Package rows the customer hasn't already booked.
 */
class UpsellCrossSellService
{
    public function recommendUpsells(Booking $booking): array
    {
        $query = UpsellOffer::where('tenant_id', $booking->tenant_id)
            ->where('is_active', true)
            ->where(function ($q) use ($booking) {
                $q->whereNull('applies_to_booking_type')->orWhere('applies_to_booking_type', $booking->booking_type);
            });

        if ($booking->package?->destination) {
            $query->where(function ($q) use ($booking) {
                $q->whereNull('applies_to_destination')->orWhere('applies_to_destination', $booking->package->destination);
            });
        }

        $offers = $query->get();

        if ($booking->visa_required) {
            $offers = $offers->reject(fn ($o) => $o->category === 'visa');
        }

        return $offers->values()->toArray();
    }

    public function recommendCrossSell(Customer $customer): array
    {
        $bookedDestinations = Booking::where('customer_id', $customer->id)
            ->whereHas('package')
            ->with('package:id,destination')
            ->get()
            ->pluck('package.destination')
            ->filter()
            ->unique();

        if ($bookedDestinations->isEmpty()) {
            return [];
        }

        $alreadyBookedPackageIds = Booking::where('customer_id', $customer->id)->pluck('package_id')->filter();

        return Package::where('tenant_id', $customer->tenant_id)
            ->where('is_active', true)
            ->where('status', 'active')
            ->whereIn('destination', $bookedDestinations)
            ->whereNotIn('id', $alreadyBookedPackageIds)
            ->get()
            ->toArray();
    }
}
