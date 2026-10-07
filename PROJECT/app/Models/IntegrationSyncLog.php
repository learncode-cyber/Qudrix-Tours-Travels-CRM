<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationSyncLog extends Model
{
    protected $table = 'integration_sync_logs';

    protected $fillable = [
        'website_integration_id',
        'sync_type',
        'entity_type',
        'entity_count',
        'status',
        'error_message',
        'started_at',
        'completed_at',
        'duration_ms',
        'data_sent',
        'data_received',
    ];

    protected $casts = [
        'data_sent' => 'json',
        'data_received' => 'json',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(WebsiteIntegration::class, 'website_integration_id');
    }

    /**
     * Mark sync as successful
     */
    public function markSuccessful(int $duration, array $data = []): void
    {
        $this->update([
            'status' => 'success',
            'completed_at' => now(),
            'duration_ms' => $duration,
            'data_received' => $data,
            'error_message' => null,
        ]);
    }

    /**
     * Mark sync as failed
     */
    public function markFailed(string $error, int $duration = 0): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'duration_ms' => $duration,
            'error_message' => $error,
        ]);
    }
}
