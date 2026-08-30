<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Polymorphic note attachable to any CRM entity (Lead, Customer, Booking, ...).
class Note extends Model
{
    protected $fillable = [
        'tenant_id', 'notable_type', 'notable_id', 'user_id', 'body', 'pinned',
    ];

    protected $casts = [
        'pinned' => 'boolean',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function notable() { return $this->morphTo(); }
}
