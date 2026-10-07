<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prediction extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'entity_type', 'entity_id', 'prediction_type', 'predicted_value', 'confidence_score', 'reasoning', 'predicted_at'];
    protected $casts = ['predicted_at' => 'datetime'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
