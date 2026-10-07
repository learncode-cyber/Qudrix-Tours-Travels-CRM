<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Follows the same 'encrypted' cast pattern established for
 * WebsiteIntegration (Phase 16 security audit) and AIProvider (Phase 9)
 * rather than a custom mutator that could silently be bypassed.
 */
class TrackingConfig extends Model
{
    protected $fillable = [
        'tenant_id', 'meta_pixel_id', 'meta_conversions_api_token',
        'ga4_measurement_id', 'ga4_api_secret', 'is_active',
    ];

    protected $hidden = ['meta_conversions_api_token', 'ga4_api_secret'];

    protected $casts = [
        'meta_conversions_api_token' => 'encrypted',
        'ga4_api_secret' => 'encrypted',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function metaConfigured(): bool
    {
        return $this->is_active && $this->meta_pixel_id && $this->meta_conversions_api_token;
    }

    public function ga4Configured(): bool
    {
        return $this->is_active && $this->ga4_measurement_id && $this->ga4_api_secret;
    }
}
