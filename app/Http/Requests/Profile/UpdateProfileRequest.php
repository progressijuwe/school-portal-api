<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'                    => ['sometimes', 'string', 'max:255'],
            'phone'                   => ['sometimes', 'nullable', 'string', 'max:20'],
            'address'                 => ['sometimes', 'nullable', 'string', 'max:500'],
            'emergency_contact_name'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.max'                          => 'Phone number must not exceed 20 characters.',
            'emergency_contact_phone.max'        => 'Emergency contact phone must not exceed 20 characters.',
            'emergency_contact_relationship.max' => 'Relationship must not exceed 100 characters.',
        ];
    }
}