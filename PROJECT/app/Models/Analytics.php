<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Analytics extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'metric_type', 'metric_value', 'period', 'recorded_date'];
    protected $casts = ['recorded_date' => 'datetime'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
