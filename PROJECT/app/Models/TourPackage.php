<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourPackage extends Model
{
    use SoftDeletes;
    protected $fillable = ['tenant_id', 'name', 'description', 'destination', 'duration_days', 'price', 'currency', 'max_capacity', 'activities', 'status'];
    protected $casts = ['activities' => 'json'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function bookings(): HasMany { return $this->hasMany(Booking::class); }
}
