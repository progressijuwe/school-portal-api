<?php

namespace App\Http\Requests\Lecturer;

use App\Http\Requests\Lecturer\Concerns\ValidatesGradeOwnership;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitGradeRequest extends FormRequest
{
    use ValidatesGradeOwnership;

    public function authorize(): bool
    {
        return $this->user()?->hasRole('lecturer') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enrollment_id' => ['required', 'integer', 'exists:enrollments,id'],
            'ca_score' => ['required', 'integer', 'min:0', 'max:'.config('academics.max_ca_score')],
            'project_score' => ['required', 'integer', 'min:0', 'max:'.config('academics.max_project_score')],
            'exam_score' => ['required', 'integer', 'min:0', 'max:'.config('academics.max_exam_score')],
        ];
    }

    /**
     * Ownership, enrollment state and "already approved" all live in
     * EnrollmentPolicy::grade, so this endpoint and the batch endpoints cannot
     * drift apart.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->assertOwnsEnrollments(
                $validator,
                [$this->input('enrollment_id')],
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ca_score.integer' => 'Scores must be whole numbers.',
            'project_score.integer' => 'Scores must be whole numbers.',
            'exam_score.integer' => 'Scores must be whole numbers.',
        ];
    }
}
