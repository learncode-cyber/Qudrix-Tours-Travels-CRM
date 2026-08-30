<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelRoomType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'hotel_id', 'name', 'capacity', 'total_rooms',
        'available_rooms', 'price_per_night', 'currency', 'amenities',
    ];

    protected $casts = [
        'amenities' => 'json',
        'price_per_night' => 'decimal:2',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function hotel(): BelongsTo { return $this->belongsTo(Hotel::class); }

    public function isAvailable(int $roomsNeeded = 1): bool
    {
        return $this->available_rooms >= $roomsNeeded;
    }
}
