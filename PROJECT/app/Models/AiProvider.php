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
        'tenant_id', 'provider', 'model', 'credentials', 'is_active',
        'is_default', 'monthly_cost_limit_usd', 'priority',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'monthly_cost_limit_usd' => 'decimal:2',
    ];

    protected $hidden = ['credentials'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function usageLogs(): HasMany { return $this->hasMany(AiUsageLog::class); }
}
