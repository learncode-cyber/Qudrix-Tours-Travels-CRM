<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIProvider extends Model
{
    // FIX: Laravel's Str::snake() turns "AIProvider" into "a_i_provider"
    // (it splits each consecutive capital), not "ai_provider" as might be
    // assumed — without this explicit override, Eloquent would look for
    // a table that doesn't exist and every query would fail.
    protected $table = 'ai_providers';

    protected $fillable = [
        'tenant_id', 'provider', 'name', 'api_key_encrypted',
        'default_model', 'max_tokens', 'temperature',
        'cost_per_1k_prompt_tokens', 'cost_per_1k_completion_tokens',
        'is_active', 'is_default', 'fallback_priority',
        'last_tested_at', 'last_test_status', 'last_test_detail',
    ];

    // SECURITY: never serialize the key, even accidentally via ->toArray()
    // or ->toJson() on a model instance. The controller layer also never
    // selects this column for list/show responses (see AIProviderController).
    protected $hidden = ['api_key_encrypted'];

    protected $casts = [
        // Laravel's built-in 'encrypted' cast — uses the app's real
        // APP_KEY via Illuminate\Encryption\Encrypter (AES-256-CBC),
        // not a custom/invented scheme. Requires APP_KEY to be set via
        // `php artisan key:generate`, same as any other Laravel app.
        'api_key_encrypted' => 'encrypted',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'temperature' => 'decimal:2',
        'last_tested_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(AIUsageLog::class);
    }
}
