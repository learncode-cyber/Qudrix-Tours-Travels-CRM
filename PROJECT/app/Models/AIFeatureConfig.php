<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIFeatureConfig extends Model
{
    protected $table = 'ai_feature_configs'; // see AIProvider for why this is needed

    protected $fillable = ['tenant_id', 'feature_key', 'ai_provider_id', 'model_override'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'ai_provider_id');
    }
}
