<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactList extends Model
{
    protected $fillable = ['tenant_id', 'name', 'description', 'is_dynamic', 'criteria'];
    protected $casts = ['is_dynamic' => 'boolean', 'criteria' => 'json'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function members(): HasMany { return $this->hasMany(ContactListMember::class); }
}
