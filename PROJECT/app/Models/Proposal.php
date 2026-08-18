<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proposal extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'quotation_id', 'lead_id', 'customer_id',
        'proposal_number', 'status', 'title', 'description',
        'proposal_date', 'expiry_date', 'sent_date', 'signed_date',
        'created_by', 'notes'
    ];

    protected $casts = [
        'proposal_date' => 'datetime',
        'expiry_date' => 'datetime',
        'sent_date' => 'datetime',
        'signed_date' => 'datetime',
    ];

    protected $dates = ['proposal_date', 'expiry_date', 'sent_date', 'signed_date'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->nullable();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isSent(): bool
    {
        return $this->sent_date !== null && $this->status !== 'draft';
    }

    public function isSigned(): bool
    {
        return $this->signed_date !== null && $this->status === 'signed';
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_date' => now()
        ]);
    }

    public function markAsSigned(): void
    {
        $this->update([
            'status' => 'signed',
            'signed_date' => now()
        ]);
    }
}
