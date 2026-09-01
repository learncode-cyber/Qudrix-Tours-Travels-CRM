<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationTemplate extends Model
{
    use SoftDeletes;
    protected $fillable = ['tenant_id', 'name', 'description', 'category', 'workflow_config', 'preview_data', 'usage_count', 'status'];
    protected $casts = ['workflow_config' => 'json', 'preview_data' => 'json'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
