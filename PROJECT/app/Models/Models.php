<?php
/**
 * QUDRIX CRM - Phase 0 Core Models Bundle
 * This file contains 7 core models used across the system
 * 
 * Each model should be extracted to its own file:
 * - Role.php
 * - Customer.php
 * - Lead.php
 * - Booking.php
 * - Payment.php
 * - Branch.php
 * - Package.php
 */

// ============================================================
// 1. ROLE MODEL
// ============================================================
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Casts\AsJson;

class Role extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'display_name', 'description',
        'is_system', 'permissions'
    ];

    protected $casts = [
        'permissions' => AsJson::class,
        'is_system' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->nullable();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    public function hasPermission($permission): bool
    {
        $permissions = $this->permissions ?? [];
        return in_array('*', $permissions) || in_array($permission, $permissions);
    }
}

// ============================================================
// 2. BRANCH MODEL
// ============================================================
class Branch extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'code', 'email', 'phone',
        'address', 'city', 'country', 'postal_code',
        'timezone', 'is_active', 'settings'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => AsJson::class,
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
}

// ============================================================
// 3. CUSTOMER MODEL
// ============================================================
class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'branch_id', 'name', 'email', 'phone',
        'customer_type', 'national_id', 'passport_number',
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
        return $this->belongsTo(Branch::class)->nullable();
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->name} ({$this->phone})";
    }
}

// ============================================================
// 4. LEAD MODEL
// ============================================================
class Lead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'branch_id', 'assigned_to', 'name',
        'email', 'phone', 'company', 'designation',
        'source', 'status', 'priority', 'notes',
        'last_contacted_at', 'follow_up_date', 'estimated_value',
        'conversion_probability'
    ];

    protected $casts = [
        'last_contacted_at' => 'datetime',
        'follow_up_date' => 'datetime',
        'estimated_value' => 'decimal:2',
    ];

    protected $dates = ['last_contacted_at', 'follow_up_date'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->nullable();
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->nullable();
    }

    public function isQualified(): bool
    {
        return $this->status === 'qualified';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' || $this->status === 'new';
    }
}

// ============================================================
// 5. PACKAGE MODEL
// ============================================================
class Package extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'code', 'type', 'description',
        'days', 'nights', 'destination', 'base_price',
        'inclusions', 'exclusions', 'is_active', 'status'
    ];

    protected $casts = [
        'inclusions' => AsJson::class,
        'exclusions' => AsJson::class,
        'is_active' => 'boolean',
        'base_price' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}

// ============================================================
// 6. BOOKING MODEL
// ============================================================
class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'customer_id', 'branch_id', 'package_id',
        'booking_reference', 'type', 'travel_date', 'return_date',
        'num_travelers', 'total_amount', 'paid_amount',
        'payment_status', 'booking_status', 'special_requests', 'metadata'
    ];

    protected $casts = [
        'travel_date' => 'datetime',
        'return_date' => 'datetime',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'metadata' => AsJson::class,
    ];

    protected $dates = ['travel_date', 'return_date'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'completed';
    }

    public function isConfirmed(): bool
    {
        return $this->booking_status === 'confirmed';
    }

    public function remainingAmount(): float
    {
        return $this->total_amount - $this->paid_amount;
    }
}

// ============================================================
// 7. PAYMENT MODEL
// ============================================================
class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'booking_id', 'amount', 'payment_method',
        'transaction_id', 'reference_number', 'status', 'paid_at', 'notes'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected $dates = ['paid_at'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}

// ============================================================
// END OF MODELS BUNDLE
// ============================================================
// Extract each class to its own file in app/Models/
// Follow naming convention: ClassName.php
