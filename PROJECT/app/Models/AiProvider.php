<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Server-side-only record of a configured AI provider/model pair. The
// `credentials` column is encrypted (see migration) and this model is never
// exposed via an API resource that serializes credentials to the frontend —
// controllers must explicitly exclude it (see AiProviderResource, added
// when the AI Provider Management phase implements the admin UI).
class AiProvider extends Model
{
    protected $fillable = [
        'tenant_id', 'provider', 'model', 'base_url', 'credentials', 'is_active',
        'is_default', 'monthly_cost_limit_usd', 'input_cost_per_million',
        'output_cost_per_million', 'max_output_tokens', 'priority',
        'last_test_at', 'last_test_error',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'monthly_cost_limit_usd' => 'decimal:2',
        'input_cost_per_million' => 'decimal:4',
        'output_cost_per_million' => 'decimal:4',
        'last_test_at' => 'datetime',
    ];

    protected $hidden = ['credentials'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function usageLogs(): HasMany { return $this->hasMany(AiUsageLog::class); }
}
