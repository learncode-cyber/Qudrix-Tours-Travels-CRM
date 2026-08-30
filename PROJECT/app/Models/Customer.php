<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\AsJson;
use App\Models\Concerns\Taggable;

class Customer extends Model
{
    use SoftDeletes, Taggable;

    protected $fillable = [
        'tenant_id', 'branch_id', 'name', 'email', 'phone',
        'customer_type', 'source', 'national_id', 'passport_number',
        'address', 'city', 'country', 'additional_info',
        'is_active', 'status'
    ];

    protected $casts = [
        'additional_info' => AsJson::class,
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class);
    }

    public function family(): HasMany
    {
        return $this->hasMany(CustomerFamily::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->name} ({$this->phone})";
    }

    public function getCommunicationCount(): int
    {
        return $this->communications()->count();
    }

    public function getRecentCommunications($limit = 5)
    {
        return $this->communications()
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function getFamilyCount(): int
    {
        return $this->family()->count();
    }
}
