<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model
{
    protected $fillable = ['tenant_id', 'name', 'holiday_date', 'is_recurring'];
    protected $casts = ['holiday_date' => 'date', 'is_recurring' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
