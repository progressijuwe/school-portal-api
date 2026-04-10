<?php

namespace App\Http\Requests\Lecturer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitGradeRequest extends FormRequest
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
            'enrollment_id' => ['required', 'integer', 'exists:enrollments,id'],
            'score'         => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->enrollment_id) {
                    $enrollment = \App\Models\Enrollment::find($this->enrollment_id);

                    // Make sure enrollment is active
                    if ($enrollment && $enrollment->status !== 'active') {
                        $validator->errors()->add(
                            'enrollment_id',
                            'Cannot submit a grade for a dropped or completed enrollment.'
                        );
                    }

                    // Make sure a grade hasn't already been approved
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
