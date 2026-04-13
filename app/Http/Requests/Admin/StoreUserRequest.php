<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email:rfc', 'unique:users,email'],
            'role'          => ['required', 'in:student,lecturer'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'study_type'    => ['required_if:role,student', 'nullable', 'in:Undergraduate,Postgraduate'],
            'entry_year'    => [
                'required_if:role,student',
                'nullable',
                'digits:4',
                'integer',
                'min:2000',
                'max:' . now()->year,
            ],
            'prefix'                => [
                'required_if:role,lecturer',
                'nullable',
                'string',
                'in:Dr.,Prof.,Mr.,Mrs.,Ms.,Engr.,Rev.',
            ],
            'highest_qualification' => [
                'required_if:role,lecturer',
                'nullable',
                'string',
                'max:100',
            ],
            'specialization'        => [
                'nullable',
                'string',
                'max:100',
            ],
            'photo'         => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in'            => 'Role must be either student or lecturer.',
            'department_id.exists'=> 'The selected department does not exist.',
            'study_type.in'      => 'Study type must be Undergraduate or Postgraduate.',
            'entry_year.max'     => 'Entry year cannot be in the future.',
        ];
    }
}