<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'webhook_id',
        'delivery_id',
        'event',
        'payload',
        'status',
        'attempt',
        'response_status',
        'response_body',
        'error_message',
        'delivered_at',
        'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'response_body' => 'array',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function webhook()
    {
        return $this->belongsTo(Webhook::class);
    }

    public function logs()
    {
        return $this->hasMany(WebhookLog::class, 'delivery_id', 'id');
    }

    public function isSuccessful()
    {
        return $this->status === 'success';
    }

    public function isFailed()
    {
        return $this->status === 'failed';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }
}
