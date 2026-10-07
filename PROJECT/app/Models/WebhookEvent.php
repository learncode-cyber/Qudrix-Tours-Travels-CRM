<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['automation_id', 'event_type', 'payload', 'processed_at', 'status'];
    protected $casts = ['payload' => 'json', 'processed_at' => 'datetime'];
    public function automation(): BelongsTo { return $this->belongsTo(Automation::class); }
}
