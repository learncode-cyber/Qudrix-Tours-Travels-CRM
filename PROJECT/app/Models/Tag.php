<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tag extends Model
{
    public $timestamps = false;

    protected $fillable = ['tenant_id', 'name', 'color'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
