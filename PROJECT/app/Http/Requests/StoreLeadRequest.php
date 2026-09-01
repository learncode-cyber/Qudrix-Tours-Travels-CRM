<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:100',
            'source' => 'required|string|max:50',
            'priority' => 'required|in:low,medium,high,urgent',
            'notes' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
            'branch_id' => 'nullable|exists:branches,id',
        ];
    }
}
