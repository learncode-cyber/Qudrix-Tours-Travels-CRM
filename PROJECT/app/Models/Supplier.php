<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Supplier extends Model
{
    use SoftDeletes;
    protected $fillable = ['tenant_id', 'name', 'type', 'email', 'phone', 'contact_person', 'commission_rate', 'contract_terms', 'status'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
