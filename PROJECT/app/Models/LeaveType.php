<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveType extends Model
{
    protected $fillable = ['tenant_id', 'name', 'annual_quota_days', 'is_paid'];
    protected $casts = ['is_paid' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
