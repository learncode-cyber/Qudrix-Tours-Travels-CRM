<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperimentVariant extends Model
{
    protected $fillable = ['experiment_id', 'name', 'content', 'views', 'engagements', 'conversions', 'revenue'];
    protected $casts = ['revenue' => 'decimal:2'];
    public function experiment(): BelongsTo { return $this->belongsTo(Experiment::class); }

    public function conversionRate(): ?float
    {
        return $this->views > 0 ? round(($this->conversions / $this->views) * 100, 2) : null;
    }
}
