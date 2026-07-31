<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkReviewEnrollmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enrollment_ids' => ['required', 'array', 'min:1', 'max:100'],
            'enrollment_ids.*' => ['required', 'integer', 'distinct', 'exists:enrollments,id'],
            'action' => ['required', 'in:approve,reject'],
        ];
    }
}
