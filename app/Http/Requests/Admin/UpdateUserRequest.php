<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * Identity fields only.
     *
     * student_id and staff_id are deliberately absent: they are generated on
     * creation, printed on ID cards, and referenced by every academic record.
     * Study type and entry year are editable because they are routinely
     * corrected after a data-entry mistake at admission.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email:rfc',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'study_type' => ['sometimes', 'nullable', 'in:Undergraduate,Postgraduate'],
            'entry_year' => [
                'sometimes',
                'nullable',
                'digits:4',
                'integer',
                'min:2000',
                'max:'.now()->year,
            ],
            'prefix' => [
                'sometimes',
                'nullable',
                'string',
                'in:Dr.,Prof.,Mr.,Mrs.,Ms.,Engr.,Rev.',
            ],
            'highest_qualification' => ['sometimes', 'nullable', 'string', 'max:100'],
            'specialization' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entry_year.max' => 'Entry year cannot be in the future.',
        ];
    }
}
