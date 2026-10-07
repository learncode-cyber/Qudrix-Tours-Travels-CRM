<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers,email',
            'phone' => 'nullable|string|max:20|regex:/^[+]?[0-9\-\s\(\)]+$/',
            'customer_type' => 'required|in:individual,corporate,group',
            'national_id' => 'nullable|string|max:50',
            'passport_number' => 'nullable|string|max:50|unique:customers',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'branch_id' => 'nullable|exists:branches,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Customer name is required',
            'phone.regex' => 'Invalid phone number format',
            'customer_type.required' => 'Customer type must be specified',
        ];
    }
}
