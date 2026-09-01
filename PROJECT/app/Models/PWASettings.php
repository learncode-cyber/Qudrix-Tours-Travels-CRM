<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PWASettings extends Model
{
    protected $table = 'pwa_settings';
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'app_name', 'app_short_name', 'description', 'icon_url', 'theme_color', 'background_color', 'is_enabled', 'offline_enabled', 'push_enabled', 'manifest_config'];
    protected $casts = ['manifest_config' => 'json'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
