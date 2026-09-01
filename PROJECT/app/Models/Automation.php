<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Automation extends Model
{
    use SoftDeletes;
    protected $fillable = ['tenant_id', 'name', 'description', 'trigger_type', 'status', 'is_active', 'run_count', 'last_run_at'];
    protected $casts = ['last_run_at' => 'datetime'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function steps(): HasMany { return $this->hasMany(AutomationStep::class); }
    public function logs(): HasMany { return $this->hasMany(AutomationLog::class); }
}
