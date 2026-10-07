<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentVisaApplication extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'lead_id', 'customer_id', 'assigned_to',
        'student_name', 'student_email', 'student_phone', 'date_of_birth',
        'nationality', 'passport_number', 'destination_country',
        'is_al_azhar', 'azhar_faculty', 'azhar_level', 'azhar_registration_number',
        'arabic_proficiency_level', 'university',
        'course', 'intake', 'status', 'application_deadline', 'appointment_date',
        'offer_letter_received', 'offer_letter_date', 'visa_status',
        'visa_decision_date', 'counselling_notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'application_deadline' => 'date',
        'appointment_date' => 'datetime',
        'offer_letter_received' => 'boolean',
        'is_al_azhar' => 'boolean',
        'offer_letter_date' => 'date',
        'visa_decision_date' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function isDeadlineApproaching(int $days = 14): bool
    {
        return $this->application_deadline
            && $this->application_deadline->isFuture()
            && now()->diffInDays($this->application_deadline) <= $days;
    }
}
