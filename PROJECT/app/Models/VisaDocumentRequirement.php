<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Admin-configurable checklist of documents required per destination
// country + visa type (Directive §3.G: "Make visa workflows configurable
// by visa type/country").
class VisaDocumentRequirement extends Model
{
    protected $fillable = ['tenant_id', 'destination_country', 'visa_type', 'document_name', 'is_mandatory'];

    protected $casts = ['is_mandatory' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
