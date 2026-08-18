<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiSetting extends Model
{
    protected $fillable = [
        'tenant_id',
        'key',
        'value',
        'type',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get setting value
     */
    public static function get(string $key, $default = null)
    {
        $setting = self::where('key', $key)
            ->where('tenant_id', auth()->user()->tenant_id ?? null)
            ->first();
        
        return $setting ? self::castValue($setting->value, $setting->type) : $default;
    }

    /**
     * Set setting value
     */
    public static function set(string $key, $value, string $type = 'string'): self
    {
        return self::updateOrCreate(
            [
                'tenant_id' => auth()->user()->tenant_id,
                'key' => $key,
            ],
            [
                'value' => $value,
                'type' => $type,
            ]
        );
    }

    /**
     * Cast value based on type
     */
    private static function castValue($value, $type)
    {
        return match($type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'float' => (float) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
