<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingTraveler extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'booking_id', 'first_name', 'last_name', 'email', 'phone',
        'date_of_birth', 'gender', 'passport_number', 'passport_expiry',
        'national_id', 'nationality', 'traveler_type', 'is_primary_contact',
        'emergency_contact', 'emergency_phone', 'room_preference'
    ];

    protected $casts = [
        'date_of_birth' => 'datetime',
        'passport_expiry' => 'datetime',
        'is_primary_contact' => 'boolean',
    ];

    protected $dates = ['date_of_birth', 'passport_expiry'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function getFullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getAge(): ?int
    {
        if (!$this->date_of_birth) return null;
        return $this->date_of_birth->age;
    }

    public function isPassportValid(): bool
    {
        if (!$this->passport_expiry) return false;
        return $this->passport_expiry > now();
    }
}
