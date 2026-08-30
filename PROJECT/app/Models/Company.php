<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'industry', 'website', 'phone', 'email',
        'address', 'notes',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function contacts(): HasMany { return $this->hasMany(Contact::class); }
}
