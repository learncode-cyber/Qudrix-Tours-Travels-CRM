<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisaApplication extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'booking_id', 'booking_traveler_id', 'destination_country',
        'visa_type', 'application_date', 'submission_date', 'approval_date',
        'visa_number', 'issue_date', 'expiry_date', 'status', 'documents',
        'notes', 'agency_name', 'agency_reference'
    ];

    protected $casts = [
        'application_date' => 'datetime',
        'submission_date' => 'datetime',
        'approval_date' => 'datetime',
        'issue_date' => 'datetime',
        'expiry_date' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(BookingTraveler::class, 'booking_traveler_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isExpired(): bool
    {
        if (!$this->expiry_date) return false;
        return $this->expiry_date < now();
    }
}
