<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'source' => $this->source,
            'status' => $this->status,
            'priority' => $this->priority,
            'conversion_probability' => $this->conversion_probability,
            'assigned_to' => $this->assignedTo?->name,
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'follow_up_date' => $this->follow_up_date?->toIso8601String(),
            'estimated_value' => $this->estimated_value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
