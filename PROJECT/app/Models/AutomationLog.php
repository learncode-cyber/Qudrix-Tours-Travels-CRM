<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['automation_id', 'trigger_data', 'status', 'result_data', 'error_message', 'execution_time_ms', 'started_at', 'completed_at'];
    protected $casts = ['trigger_data' => 'json', 'result_data' => 'json', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    public function automation(): BelongsTo { return $this->belongsTo(Automation::class); }
}
