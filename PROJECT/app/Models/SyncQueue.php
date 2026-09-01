<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncQueue extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'user_id', 'batch_id', 'data', 'status', 'retry_count', 'last_error', 'queued_at', 'processed_at'];
    protected $casts = ['data' => 'json', 'queued_at' => 'datetime', 'processed_at' => 'datetime'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
