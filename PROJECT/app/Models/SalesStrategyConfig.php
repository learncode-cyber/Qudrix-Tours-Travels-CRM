<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesStrategyConfig extends Model
{
    protected $fillable = ['tenant_id', 'strategy'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
