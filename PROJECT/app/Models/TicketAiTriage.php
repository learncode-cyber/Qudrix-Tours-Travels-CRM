<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAiTriage extends Model
{
    protected $fillable = [
        'tenant_id', 'support_ticket_id', 'suggested_severity', 'suggested_category',
        'suggested_response', 'suggested_resolution', 'recommends_escalation',
        'escalation_reason', 'sentiment', 'detected_issues', 'applied_by', 'applied_at',
    ];

    protected $casts = [
        'recommends_escalation' => 'boolean',
        'detected_issues' => 'json',
        'applied_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function ticket(): BelongsTo { return $this->belongsTo(SupportTicket::class, 'support_ticket_id'); }
    public function appliedBy(): BelongsTo { return $this->belongsTo(User::class, 'applied_by'); }

    public function isApplied(): bool
    {
        return $this->applied_at !== null;
    }
}
