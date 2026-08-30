<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentVisaApplication extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'lead_id', 'customer_id', 'student_name', 'date_of_birth',
        'destination_country', 'university', 'course', 'intake',
        'application_status', 'offer_letter_received', 'offer_letter_date',
        'embassy_appointment_date', 'visa_status', 'assigned_counsellor_id',
        'service_fee', 'service_fee_currency', 'payment_status', 'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'offer_letter_received' => 'boolean',
        'offer_letter_date' => 'date',
        'embassy_appointment_date' => 'datetime',
        'service_fee' => 'decimal:2',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function counsellor(): BelongsTo { return $this->belongsTo(User::class, 'assigned_counsellor_id'); }
}
