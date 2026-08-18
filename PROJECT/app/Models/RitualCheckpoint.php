<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RitualCheckpoint extends Model
{
    public $timestamps = false;
    protected $fillable = ['booking_id', 'ritual_name', 'status', 'completed_date', 'notes'];
    protected $casts = ['completed_date' => 'datetime'];
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
}
