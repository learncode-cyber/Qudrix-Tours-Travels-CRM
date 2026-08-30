<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisaChecklistItem extends Model
{
    protected $fillable = [
        'visa_application_id', 'visa_document_requirement_id', 'document_name',
        'status', 'submitted_at', 'verified_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function application(): BelongsTo { return $this->belongsTo(VisaApplication::class, 'visa_application_id'); }
    public function requirement(): BelongsTo { return $this->belongsTo(VisaDocumentRequirement::class, 'visa_document_requirement_id'); }
}
