<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentPayout extends Model
{
    protected $fillable = [
        'tenant_id', 'agent_id', 'amount', 'currency', 'method', 'reference',
        'status', 'paid_at', 'processed_by', 'note',
    ];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function processedBy(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }
    public function commissions(): HasMany { return $this->hasMany(AgentCommission::class, 'agent_payout_id'); }
}
