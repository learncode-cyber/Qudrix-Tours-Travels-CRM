<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelBookingExtraService extends Model
{
    public $timestamps = false;

    protected $fillable = ['hotel_booking_id', 'hotel_extra_service_id', 'quantity', 'price'];

    protected $casts = ['price' => 'decimal:2'];

    public function hotelBooking(): BelongsTo { return $this->belongsTo(HotelBooking::class); }
    public function extraService(): BelongsTo { return $this->belongsTo(HotelExtraService::class, 'hotel_extra_service_id'); }
}
