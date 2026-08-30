<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiConnectorCallLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'api_connector_id', 'user_id', 'operation', 'http_method',
        'url', 'request_payload', 'response_status', 'response_body',
        'duration_ms', 'success', 'error_message', 'created_at',
    ];

    protected $casts = [
        'request_payload' => 'json',
        'success' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function connector(): BelongsTo { return $this->belongsTo(ApiConnector::class, 'api_connector_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
