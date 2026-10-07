<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoMetadata extends Model
{
    protected $table = 'seo_metadata';
    protected $fillable = [
        'tenant_id', 'entity_type', 'entity_id', 'slug', 'meta_title',
        'meta_description', 'og_title', 'og_description', 'og_image_url',
        'canonical_url', 'schema_markup', 'robots_directive',
    ];
    protected $casts = ['schema_markup' => 'json'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
