<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationDashboard extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'user_id', 'name', 'widgets', 'refresh_interval'];
    protected $casts = ['widgets' => 'json'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
