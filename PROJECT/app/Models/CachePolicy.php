<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CachePolicy extends Model
{
    use SoftDeletes;
    protected $fillable = ['tenant_id', 'resource_type', 'cache_strategy', 'ttl_minutes', 'max_size_mb', 'priority', 'is_active'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
