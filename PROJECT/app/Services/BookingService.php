<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\BookingItinerary;
use App\Models\BookingConfirmation;

class BookingService
{
    public function createBooking(int $tenantId, array $data)
    {
        $booking = Booking::create([
            'tenant_id' => $tenantId,
            'booking_number' => 'BK-' . time(),
            'status' => 'pending',
            'payment_status' => 'pending',
            ...$data
        ]);

        return $booking;
    }

    public function confirmBooking(Booking $booking, int $userId)
    {
        $booking->markAsConfirmed();

        BookingConfirmation::create([
            'tenant_id' => $booking->tenant_id,
            'booking_id' => $booking->id,
            'confirmation_number' => 'CONF-' . time(),
            'confirmation_date' => now(),
            'confirmed_by' => $userId,
            'confirmation_method' => 'system',
        ]);

        return $booking;
    }

    public function getUpcomingBookings(int $tenantId)
    {
        return Booking::where('tenant_id', $tenantId)
            ->where('travel_date', '>', now())
            ->where('status', 'confirmed')
            ->orderBy('travel_date')
            ->get();
    }

    public function getBookingAnalytics(int $tenantId)
    {
        $bookings = Booking::where('tenant_id', $tenantId)->get();

        return [
            'total_bookings' => $bookings->count(),
            'confirmed_bookings' => $bookings->where('status', 'confirmed')->count(),
            'total_revenue' => $bookings->where('status', 'confirmed')->sum('total_amount'),
            'average_booking_value' => $bookings->where('status', 'confirmed')->avg('total_amount'),
            'total_travelers' => BookingTraveler::whereIn('booking_id', $bookings->pluck('id'))->count(),
            'confirmation_rate' => $bookings->count() > 0 
                ? round(($bookings->where('status', 'confirmed')->count() / $bookings->count()) * 100, 2)
                : 0,
        ];
    }
}
