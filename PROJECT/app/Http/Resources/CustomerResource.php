<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'customer_type' => $this->customer_type,
            'status' => $this->status,
            'country' => $this->country,
            'city' => $this->city,
            'is_active' => $this->is_active,
            'family_count' => $this->family()->count(),
            'booking_count' => $this->bookings()->count(),
            'communication_count' => $this->communications()->count(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
