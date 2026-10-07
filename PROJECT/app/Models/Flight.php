<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Flight extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'booking_id', 'airline_code', 'flight_number',
        'departure_airport', 'arrival_airport', 'departure_date',
        'arrival_date', 'departure_time', 'arrival_time',
        'aircraft_type', 'total_seats', 'available_seats',
        'price_per_seat', 'currency', 'status', 'notes'
    ];

    protected $casts = [
        'departure_date' => 'datetime',
        'arrival_date' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function flightBookings(): HasMany
    {
        return $this->hasMany(FlightBooking::class);
    }

    public function getFlightDuration(): ?string
    {
        if (!$this->departure_date || !$this->arrival_date) return null;
        return $this->departure_date->diff($this->arrival_date)->format('%H:%I');
    }

    public function isAvailable(): bool
    {
        return $this->available_seats > 0 && $this->status === 'active';
    }
}
