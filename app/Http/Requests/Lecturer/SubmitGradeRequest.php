<?php

namespace App\Http\Requests\Lecturer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enrollment_id' => ['required', 'integer', 'exists:enrollments,id'],
            'ca_score'      => ['required', 'numeric', 'min:0', 'max:20'],
            'project_score' => ['required', 'numeric', 'min:0', 'max:20'],
            'exam_score'    => ['required', 'numeric', 'min:0', 'max:60'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->enrollment_id) {
                    $enrollment = \App\Models\Enrollment::find($this->enrollment_id);

                    if ($enrollment && $enrollment->status !== 'active') {
                        $validator->errors()->add(
                            'enrollment_id',
                            'Cannot submit a grade for a dropped or completed enrollment.'
                        );
                    }

                    if ($enrollment && $enrollment->grade?->status === 'approved') {
                        $validator->errors()->add(
                            'enrollment_id',
                            'A grade has already been approved for this enrollment.'
                        );
                    }
                }
            },
        ];
    }
}