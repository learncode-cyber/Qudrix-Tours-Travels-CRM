<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    protected $fillable = [
        'conversation_id', 'direction', 'sender_user_id', 'body', 'attachments',
        'is_internal_note', 'external_message_id', 'delivery_status', 'delivery_error', 'read_at',
    ];

    protected $casts = [
        'attachments' => 'json',
        'is_internal_note' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_user_id'); }
}
