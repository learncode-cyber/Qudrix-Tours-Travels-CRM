<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbExperiment extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'hypothesis', 'subject_type', 'status', 'started_at', 'stopped_at',
    ];

    protected $casts = ['started_at' => 'datetime', 'stopped_at' => 'datetime'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function variants(): HasMany { return $this->hasMany(AbVariant::class); }
    public function assignments(): HasMany { return $this->hasMany(AbAssignment::class); }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }
}
