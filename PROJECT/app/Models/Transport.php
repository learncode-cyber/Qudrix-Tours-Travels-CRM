<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'booking_id', 'transport_type', 'vehicle_name',
        'vehicle_number', 'pickup_location', 'dropoff_location',
        'pickup_date', 'pickup_time', 'dropoff_time', 'capacity',
        'price_per_seat', 'currency', 'driver_name', 'driver_phone',
        'status', 'notes'
    ];

    protected $casts = [
        'pickup_date' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function transportBookings(): HasMany
    {
        return $this->hasMany(TransportBooking::class);
    }
}
