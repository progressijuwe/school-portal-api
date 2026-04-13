<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseRequest extends FormRequest
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
        $courseId = $this->route('course')?->id;

        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'title'         => ['required', 'string', 'max:255'],
            'code'          => [
                'required',
                'string',
                'max:20',
                Rule::unique('courses', 'code')->ignore($courseId),
            ],
            'credit_units'  => ['required', 'integer', 'min:1', 'max:6'],
            'level'         => ['required', 'in:100,200,300,400,500'],
            'semester'      => ['required', 'in:first,second'],
            'type'          => ['required', 'in:compulsory,elective'],
            'description'   => ['nullable', 'string', 'max:1000'],
        ];
    }
}
