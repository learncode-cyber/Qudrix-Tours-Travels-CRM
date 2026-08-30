<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// An operator-configured external API provider. Credentials are encrypted
// at rest and hidden from every serialization — they exist only to be
// read server-side by ApiConnectorService when building a request.
class ApiConnector extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'flight', 'hotel', 'visa', 'payment', 'sms', 'whatsapp',
        'email', 'ai', 'analytics', 'crm', 'other',
    ];

    public const AUTH_TYPES = [
        'none', 'bearer', 'api_key_header', 'api_key_query', 'basic', 'custom_headers',
    ];

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'category', 'provider_name', 'base_url',
        'auth_type', 'auth_key_name', 'credentials', 'default_headers',
        'timeout_seconds', 'is_active', 'status', 'last_test_at', 'last_test_error',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'default_headers' => 'json',
        'is_active' => 'boolean',
        'last_test_at' => 'datetime',
    ];

    protected $hidden = ['credentials'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function endpoints(): HasMany { return $this->hasMany(ApiConnectorEndpoint::class); }
    public function callLogs(): HasMany { return $this->hasMany(ApiConnectorCallLog::class); }

    // A connector the operator has not yet supplied an actual contract for
    // (no endpoints mapped) is reported as CONTRACT REQUIRED rather than
    // pretending it can do anything.
    public function isContractRequired(): bool
    {
        return $this->endpoints()->where('is_active', true)->count() === 0;
    }

    public function endpointFor(string $operation): ?ApiConnectorEndpoint
    {
        return $this->endpoints()->where('operation', $operation)->where('is_active', true)->first();
    }
}
