<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    use SoftDeletes;
    protected $fillable = ['tenant_id', 'name', 'description', 'report_type', 'filters', 'generated_at', 'file_path', 'status'];
    protected $casts = ['filters' => 'json', 'generated_at' => 'datetime'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function schedules(): HasMany { return $this->hasMany(ReportSchedule::class); }
}
