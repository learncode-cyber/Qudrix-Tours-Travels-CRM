<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiConnectorEndpoint extends Model
{
    protected $fillable = [
        'api_connector_id', 'operation', 'http_method', 'path',
        'request_template', 'query_template', 'response_mapping',
        'response_collection_path', 'is_active',
    ];

    protected $casts = [
        'request_template' => 'json',
        'query_template' => 'json',
        'response_mapping' => 'json',
        'is_active' => 'boolean',
    ];

    public function connector(): BelongsTo { return $this->belongsTo(ApiConnector::class, 'api_connector_id'); }
}
