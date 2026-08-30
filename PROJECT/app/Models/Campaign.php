<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    public const CHANNELS = ['email', 'sms', 'whatsapp'];

    protected $fillable = [
        'tenant_id', 'contact_list_id', 'name', 'channel', 'subject', 'body',
        'status', 'scheduled_at', 'started_at', 'completed_at', 'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function contactList(): BelongsTo { return $this->belongsTo(ContactList::class); }
    public function recipients(): HasMany { return $this->hasMany(CampaignRecipient::class); }

    /** Real counts from actual recipient rows, never estimated. */
    public function stats(): array
    {
        $total = $this->recipients()->count();
        $sent = $this->recipients()->where('status', 'sent')->count();
        $failed = $this->recipients()->where('status', 'failed')->count();
        $skipped = $this->recipients()->where('status', 'skipped')->count();

        return [
            'total_recipients' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'pending' => $this->recipients()->where('status', 'pending')->count(),
            'delivery_rate_percent' => $total > 0 ? round(($sent / $total) * 100, 2) : null,
        ];
    }
}
