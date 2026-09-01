<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSegment extends Model
{
    use SoftDeletes;
    protected $fillable = ['tenant_id', 'name', 'criteria', 'member_count', 'description', 'status'];
    protected $casts = ['criteria' => 'json'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
