<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesActivity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'lead_id', 'user_id', 'activity_type',
        'title', 'description', 'activity_date', 'outcome'
    ];

    protected $casts = [
        'activity_date' => 'datetime',
    ];

    protected $dates = ['activity_date'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
