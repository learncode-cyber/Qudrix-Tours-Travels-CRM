<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelExtraService extends Model
{
    protected $fillable = ['tenant_id', 'hotel_id', 'name', 'price', 'currency', 'is_active'];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function hotel(): BelongsTo { return $this->belongsTo(Hotel::class); }
}
