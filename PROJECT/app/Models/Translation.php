<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// Generic polymorphic translation store for translatable business content
// (e.g. TourPackage name/description, Destination name) so entities are not
// forced into duplicate per-language columns. UI static strings are handled
// separately by the frontend i18n bundles, not through this table.
class Translation extends Model
{
    protected $fillable = [
        'tenant_id', 'translatable_type', 'translatable_id', 'field', 'locale', 'value',
    ];

    public function translatable()
    {
        return $this->morphTo();
    }
}
