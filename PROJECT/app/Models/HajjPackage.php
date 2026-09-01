<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HajjPackage extends Model
{
    use SoftDeletes;
    protected $fillable = ['tenant_id', 'name', 'description', 'duration_days', 'price', 'currency', 'max_capacity', 'rituals_included', 'accommodations', 'status'];
    protected $casts = ['rituals_included' => 'json', 'accommodations' => 'json'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function bookings(): HasMany { return $this->hasMany(Booking::class); }
}
