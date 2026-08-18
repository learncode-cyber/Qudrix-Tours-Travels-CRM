<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineData extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'user_id', 'data_type', 'data', 'size_kb', 'last_synced', 'sync_status'];
    protected $casts = ['data' => 'json', 'last_synced' => 'datetime'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
