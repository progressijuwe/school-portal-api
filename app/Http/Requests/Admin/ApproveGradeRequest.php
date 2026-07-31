<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApproveGradeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:approve,reject'],
            'rejection_reason' => ['required_if:action,reject', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required_if' => 'A rejection reason is required when rejecting a grade.',
        ];
    }
}
