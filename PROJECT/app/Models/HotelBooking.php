<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelBooking extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'booking_id', 'hotel_id', 'check_in_date', 'check_out_date',
        'number_of_rooms', 'number_of_nights', 'room_type',
        'price_per_night', 'total_price', 'status', 'confirmation_number'
    ];

    protected $casts = [
        'check_in_date' => 'datetime',
        'check_out_date' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function getNights(): int
    {
        return $this->check_in_date->diffInDays($this->check_out_date);
    }
}
