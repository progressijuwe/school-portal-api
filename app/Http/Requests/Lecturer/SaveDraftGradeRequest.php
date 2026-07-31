<?php

namespace App\Http\Requests\Lecturer;

use App\Http\Requests\Lecturer\Concerns\ValidatesGradeOwnership;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveDraftGradeRequest extends FormRequest
{
    use ValidatesGradeOwnership;

    public function authorize(): bool
    {
        return $this->user()?->hasRole('lecturer') ?? false;
    }

    /**
     * Components are nullable here — a draft is a partially filled mark sheet.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'grades' => ['required', 'array', 'min:1', 'max:500'],
            'grades.*.enrollment_id' => ['required', 'integer', 'distinct', 'exists:enrollments,id'],
            'grades.*.ca_score' => ['nullable', 'integer', 'min:0', 'max:'.config('academics.max_ca_score')],
            'grades.*.project_score' => ['nullable', 'integer', 'min:0', 'max:'.config('academics.max_project_score')],
            'grades.*.exam_score' => ['nullable', 'integer', 'min:0', 'max:'.config('academics.max_exam_score')],
        ];
    }

    /**
     * Drafts were previously the one grading path with no guard at all — not
     * even an enrollment status check.
     *
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
        ];
    }
}
