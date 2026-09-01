<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dashboard extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'name', 'widgets', 'layout', 'is_default'];
    protected $casts = ['widgets' => 'json', 'layout' => 'json'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
