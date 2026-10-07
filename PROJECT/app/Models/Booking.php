<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\AsJson;

class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'lead_id', 'customer_id', 'package_id', 'agent_id',
        'quotation_id', 'proposal_id',
        'created_by', 'booking_number', 'booking_type', 'group_booking_id',
        'status', 'travel_date', 'return_date',
        'number_of_travelers', 'total_amount', 'currency',
        'payment_status', 'confirmation_date', 'notes',
        'special_requests', 'visa_required'
    ];

    protected $casts = [
        'travel_date' => 'datetime',
        'return_date' => 'datetime',
        'confirmation_date' => 'datetime',
        'special_requests' => AsJson::class,
    ];

    protected $dates = ['travel_date', 'return_date', 'confirmation_date'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function installmentPlans(): HasMany
    {
        return $this->hasMany(InstallmentPlan::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function travelers(): HasMany
    {
        return $this->hasMany(BookingTraveler::class);
    }

    public function itinerary(): HasMany
    {
        return $this->hasMany(BookingItinerary::class);
    }

    /**
     * FIX (frontend integration audit, 2026-09-20): needed to let a
     * booking's own detail view show/manage its hotel stays (and from
     * there, room assignments) — no relation existed even though
     * HotelBooking::booking_id already links back correctly.
     */
    public function hotelBookings(): HasMany
    {
        return $this->hasMany(HotelBooking::class);
    }

    /**
     * FIX (frontend integration audit, 2026-09-20): same gap as
     * hotelBookings — FlightBooking/TransportBooking::booking_id already
     * linked back correctly, but nothing on Booking exposed it, so a
     * booking's own detail view had no way to show/manage flight or
     * transport bookings for its travelers.
     */
    public function flightBookings(): HasMany
    {
        return $this->hasMany(FlightBooking::class);
    }

    public function transportBookings(): HasMany
    {
        return $this->hasMany(TransportBooking::class);
    }

    public function confirmation(): BelongsTo
    {
        return $this->belongsTo(BookingConfirmation::class, 'id', 'booking_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(GroupBooking::class, 'group_booking_id');
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed' && $this->confirmation_date !== null;
    }

    public function markAsConfirmed(): void
    {
        $this->update([
            'status' => 'confirmed',
            'confirmation_date' => now()
        ]);
    }

    public function getTotalTravelers(): int
    {
        return $this->travelers()->count();
    }

    public function getDaysUntilTravel(): ?int
    {
        if (!$this->travel_date) return null;
        return now()->diffInDays($this->travel_date);
    }
}
