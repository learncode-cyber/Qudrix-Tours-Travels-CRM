<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesScript extends Model
{
    protected $fillable = ['tenant_id', 'category', 'title', 'content', 'strategy'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
