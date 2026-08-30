<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Vendor is kept distinct from Supplier: a Vendor sells packaged
// products/services (e.g. Hajj/Umrah operators, DMCs) under a contract,
// while a Supplier provides raw inventory (hotel rooms, flight seats).
class Vendor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'category', 'email', 'phone', 'contact_person',
        'address', 'contract_start_date', 'contract_end_date', 'contract_terms',
        'commission_rate', 'payment_terms', 'status', 'rating',
    ];

    protected $casts = [
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'commission_rate' => 'decimal:2',
        'rating' => 'decimal:2',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function payments(): HasMany { return $this->hasMany(VendorPayment::class); }
}
