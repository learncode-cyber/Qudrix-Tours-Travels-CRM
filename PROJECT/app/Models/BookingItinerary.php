<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingItinerary extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'booking_id', 'day_number', 'date', 'location',
        'activity_type', 'activity_name', 'description',
        'start_time', 'end_time', 'hotel_name', 'meal_type',
        'transportation_type', 'notes'
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    protected $dates = ['date'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function getActivityDuration(): ?string
    {
        if (!$this->start_time || !$this->end_time) return null;
        
        $start = \Carbon\Carbon::createFromFormat('H:i:s', $this->start_time);
        $end = \Carbon\Carbon::createFromFormat('H:i:s', $this->end_time);
        
        return $start->diffForHumans($end);
    }
}
