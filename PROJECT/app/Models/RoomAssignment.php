<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RoomAssignment extends Model
{
    protected $fillable = [
        'tenant_id', 'hotel_booking_id', 'room_number', 'room_type', 'max_occupancy', 'notes',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function hotelBooking(): BelongsTo
    {
        return $this->belongsTo(HotelBooking::class);
    }

    public function travelers(): BelongsToMany
    {
        return $this->belongsToMany(BookingTraveler::class, 'room_assignment_traveler')->withTimestamps();
    }

    public function isOverCapacity(): bool
    {
        return $this->travelers()->count() > $this->max_occupancy;
    }
}
