<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationStep extends Model
{
    public $timestamps = false;
    protected $fillable = ['automation_id', 'step_order', 'action_type', 'action_config', 'condition_type', 'condition_config', 'delay_seconds'];
    protected $casts = ['action_config' => 'json', 'condition_config' => 'json'];
    public function automation(): BelongsTo { return $this->belongsTo(Automation::class); }
}
