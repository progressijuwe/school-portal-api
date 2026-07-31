<?php

namespace App\Http\Requests\Lecturer;

use App\Http\Requests\Lecturer\Concerns\ValidatesGradeOwnership;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BatchSubmitGradeRequest extends FormRequest
{
    use ValidatesGradeOwnership;

    public function authorize(): bool
    {
        return $this->user()?->hasRole('lecturer') ?? false;
    }

    /**
     * Component maxima come from config so the grading scheme is stated once.
     *
     * Scores are validated as integers, not `numeric`: the underlying columns
     * are unsignedTinyInteger, so a submitted 17.5 was silently truncated to 17
     * and shifted the letter grade.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'grades' => ['required', 'array', 'min:1', 'max:500'],
            'grades.*.enrollment_id' => ['required', 'integer', 'distinct', 'exists:enrollments,id'],
            'grades.*.ca_score' => ['required', 'integer', 'min:0', 'max:'.config('academics.max_ca_score')],
            'grades.*.project_score' => ['required', 'integer', 'min:0', 'max:'.config('academics.max_project_score')],
            'grades.*.exam_score' => ['required', 'integer', 'min:0', 'max:'.config('academics.max_exam_score')],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->assertOwnsEnrollments(
                $validator,
                collect($this->input('grades', []))->pluck('enrollment_id')->all(),
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grades.*.enrollment_id.distinct' => 'The same student appears more than once in this submission.',
            'grades.*.ca_score.integer' => 'Scores must be whole numbers.',
            'grades.*.project_score.integer' => 'Scores must be whole numbers.',
            'grades.*.exam_score.integer' => 'Scores must be whole numbers.',
        ];
    }
}
