<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSchedule extends Model
{
    public $timestamps = false;
    protected $fillable = ['report_id', 'frequency', 'recipients', 'next_run_at', 'is_active'];
    protected $casts = ['recipients' => 'json', 'next_run_at' => 'datetime'];
    public function report(): BelongsTo { return $this->belongsTo(Report::class); }
}
