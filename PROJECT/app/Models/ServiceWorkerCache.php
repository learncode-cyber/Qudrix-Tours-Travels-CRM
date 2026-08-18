<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceWorkerCache extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'cache_name', 'resource_url', 'status', 'cached_at', 'expires_at'];
    protected $casts = ['cached_at' => 'datetime', 'expires_at' => 'datetime'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
