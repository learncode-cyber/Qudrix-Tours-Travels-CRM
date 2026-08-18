<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupBooking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'group_name', 'group_leader_id',
        'total_members', 'status', 'description',
        'created_by'
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function groupLeader(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'group_leader_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function getBookingCount(): int
    {
        return $this->bookings()->count();
    }

    public function getTotalTravelers(): int
    {
        return $this->bookings()->sum('number_of_travelers');
    }
}
