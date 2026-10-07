<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UmrahPackage extends Model
{
    use SoftDeletes;
    protected $fillable = ['tenant_id', 'name', 'description', 'duration_days', 'price', 'currency', 'max_capacity', 'rituals_included', 'status'];
    protected $casts = ['rituals_included' => 'json'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
