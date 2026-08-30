<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Polymorphic document attachable to any CRM/booking entity. Actual file
// storage goes through Laravel's filesystem (config('filesystems')) —
// file_path is the storage-disk-relative path, not a public URL.
class Document extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'documentable_type', 'documentable_id', 'uploaded_by',
        'file_name', 'file_path', 'disk', 'file_type', 'file_size', 'category',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function documentable() { return $this->morphTo(); }
}
