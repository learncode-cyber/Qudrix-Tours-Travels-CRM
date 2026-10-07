<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObjectionResponse extends Model
{
    protected $fillable = ['tenant_id', 'objection', 'suggested_response'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
