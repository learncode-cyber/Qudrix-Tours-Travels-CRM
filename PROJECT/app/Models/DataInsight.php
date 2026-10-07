<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataInsight extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'insight_type', 'title', 'description', 'data', 'impact_level', 'recommended_action', 'generated_at'];
    protected $casts = ['data' => 'json', 'generated_at' => 'datetime'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
