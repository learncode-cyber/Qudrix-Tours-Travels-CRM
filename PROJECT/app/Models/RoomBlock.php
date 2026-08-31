<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomBlock extends Model
{
    protected $fillable = [
        'tenant_id', 'hotel_id', 'hotel_room_type_id', 'group_booking_id',
        'name', 'blocked_rooms', 'released_rooms', 'start_date', 'end_date',
        'status', 'notes',
    ];

    protected $casts = [
        'blocked_rooms' => 'integer',
        'released_rooms' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(HotelRoomType::class, 'hotel_room_type_id');
    }

    public function groupBooking(): BelongsTo
    {
        return $this->belongsTo(GroupBooking::class);
    }

    public function remainingRooms(): int
    {
        return max(0, $this->blocked_rooms - $this->released_rooms);
    }
}
