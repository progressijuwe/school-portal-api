<?php

namespace App\Http\Requests\Admin;

use App\Enums\CourseType;
use App\Enums\Semester;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
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
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('courses', 'code')->ignore($courseId),
            ],
            'credit_units' => ['sometimes', 'integer', 'min:1', 'max:6'],
            'level' => ['sometimes', 'in:100,200,300,400,500'],
            'semester' => ['sometimes', Rule::enum(Semester::class)],
            'type' => ['sometimes', Rule::enum(CourseType::class)],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
