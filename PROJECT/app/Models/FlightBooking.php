<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlightBooking extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'booking_id', 'flight_id', 'seat_number', 'booking_traveler_id',
        'ticket_number', 'pnr', 'cabin_class', 'baggage_allowance', 'fare_type',
        'status', 'price_paid', 'refund_status', 'refund_amount', 'cancelled_at',
    ];

    protected $casts = [
        'price_paid' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class);
    }

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(BookingTraveler::class, 'booking_traveler_id');
    }
}
