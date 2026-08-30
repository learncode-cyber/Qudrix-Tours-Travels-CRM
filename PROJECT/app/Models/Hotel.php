<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'city', 'country', 'address',
        'phone', 'email', 'website', 'star_rating', 'description',
        'total_rooms', 'available_rooms', 'price_per_night',
        'currency', 'check_in_time', 'check_out_time', 'status'
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function hotelBookings(): HasMany
    {
        return $this->hasMany(HotelBooking::class);
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(HotelRoomType::class);
    }

    public function extraServices(): HasMany
    {
        return $this->hasMany(HotelExtraService::class);
    }

    public function isAvailable(): bool
    {
        return $this->available_rooms > 0 && $this->status === 'active';
    }
}
