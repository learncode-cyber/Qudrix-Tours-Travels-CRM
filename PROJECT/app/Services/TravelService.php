<?php

namespace App\Services;

use App\Models\Flight;
use App\Models\Hotel;
use App\Models\Transport;
use App\Models\VisaApplication;

class TravelService
{
    public function searchFlights($departure, $arrival, $date, $tenantId)
    {
        return Flight::where('tenant_id', $tenantId)
            ->where('departure_airport', $departure)
            ->where('arrival_airport', $arrival)
            ->whereDate('departure_date', $date)
            ->where('available_seats', '>', 0)
            ->get();
    }

    public function searchHotels($city, $checkIn, $checkOut, $tenantId)
    {
        return Hotel::where('tenant_id', $tenantId)
            ->where('city', $city)
            ->where('available_rooms', '>', 0)
            ->get();
    }

    public function getVisaStatus($bookingId, $tenantId)
    {
        $visas = VisaApplication::where('tenant_id', $tenantId)
            ->where('booking_id', $bookingId)
            ->get();

        return [
            'total' => $visas->count(),
            'approved' => $visas->where('status', 'approved')->count(),
            'pending' => $visas->where('status', 'pending')->count(),
            'expired' => $visas->filter(fn($v) => $v->isExpired())->count(),
        ];
    }
}
