<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    use SoftDeletes;
    protected $fillable = ['tenant_id', 'booking_id', 'customer_id', 'title', 'description', 'category', 'priority', 'status', 'assigned_to', 'resolution', 'resolution_date'];
    protected $casts = ['resolution_date' => 'datetime'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
