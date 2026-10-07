<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingConfirmation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'booking_id', 'confirmation_number',
        'confirmation_date', 'confirmed_by', 'confirmation_method',
        'reference_code', 'provider_confirmation_id', 'notes'
    ];

    protected $casts = [
        'confirmation_date' => 'datetime',
    ];

    protected $dates = ['confirmation_date'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
