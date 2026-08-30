<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pilgrim extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'hajj_umrah_group_id', 'booking_id', 'customer_id', 'name',
        'passport_number', 'passport_expiry', 'gender', 'date_of_birth',
        'mahram_name', 'room_number', 'hotel_id', 'transport_assignment',
        'visa_application_id', 'payment_status', 'amount_due', 'amount_paid', 'status',
    ];

    protected $casts = [
        'passport_expiry' => 'date',
        'date_of_birth' => 'date',
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function group(): BelongsTo { return $this->belongsTo(HajjUmrahGroup::class, 'hajj_umrah_group_id'); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function hotel(): BelongsTo { return $this->belongsTo(Hotel::class); }
    public function visaApplication(): BelongsTo { return $this->belongsTo(VisaApplication::class); }

    public function balanceDue(): float
    {
        return round((float) $this->amount_due - (float) $this->amount_paid, 2);
    }
}
