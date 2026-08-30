<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    public const CHANNELS = ['website_chat', 'email', 'whatsapp', 'telegram', 'sms', 'internal'];

    protected $fillable = [
        'tenant_id', 'customer_id', 'lead_id', 'channel', 'external_thread_id',
        'subject', 'status', 'assigned_to', 'last_message_at', 'unread_count',
    ];

    protected $casts = ['last_message_at' => 'datetime'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function messages(): HasMany { return $this->hasMany(ConversationMessage::class); }
}
