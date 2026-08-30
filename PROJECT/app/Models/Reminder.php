<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'remindable_type', 'remindable_id', 'title',
        'remind_at', 'status',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function remindable() { return $this->morphTo(); }

    public function isDue(): bool
    {
        return $this->status === 'pending' && $this->remind_at <= now();
    }
}
