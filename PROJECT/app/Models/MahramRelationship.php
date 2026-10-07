<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MahramRelationship extends Model
{
    protected $fillable = [
        'tenant_id', 'booking_traveler_id', 'mahram_traveler_id',
        'mahram_name', 'mahram_phone', 'mahram_passport_number',
        'relationship_type', 'is_verified', 'verified_by', 'verified_at', 'notes',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(BookingTraveler::class, 'booking_traveler_id');
    }

    public function mahramTraveler(): BelongsTo
    {
        return $this->belongsTo(BookingTraveler::class, 'mahram_traveler_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** True if either a co-traveler mahram or a documented external mahram is on file. */
    public function hasMahramOnFile(): bool
    {
        return (bool) ($this->mahram_traveler_id || $this->mahram_name);
    }
}
