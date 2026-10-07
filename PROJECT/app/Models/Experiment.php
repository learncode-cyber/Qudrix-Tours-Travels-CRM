<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Experiment extends Model
{
    protected $fillable = ['tenant_id', 'name', 'subject_type', 'status', 'winning_variant_id', 'started_at', 'ended_at'];
    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function variants(): HasMany { return $this->hasMany(ExperimentVariant::class); }
    public function winningVariant(): BelongsTo { return $this->belongsTo(ExperimentVariant::class, 'winning_variant_id'); }
}
