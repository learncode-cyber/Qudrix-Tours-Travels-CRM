<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbVariant extends Model
{
    protected $fillable = ['ab_experiment_id', 'label', 'content', 'weight', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function experiment(): BelongsTo { return $this->belongsTo(AbExperiment::class, 'ab_experiment_id'); }
    public function assignments(): HasMany { return $this->hasMany(AbAssignment::class); }
}
