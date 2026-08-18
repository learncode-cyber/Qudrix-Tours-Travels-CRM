<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineSync extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'user_id', 'entity_type', 'entity_id', 'operation', 'payload', 'status', 'synced_at', 'created_at'];
    protected $casts = ['payload' => 'json', 'synced_at' => 'datetime', 'created_at' => 'datetime'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
