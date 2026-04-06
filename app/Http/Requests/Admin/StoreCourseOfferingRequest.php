<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseOfferingRequest extends FormRequest
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
            'course_id'           => ['required', 'integer', 'exists:courses,id'],
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'lecturer_id'         => ['nullable', 'integer', 'exists:users,id'],
            'semester'            => ['required', 'in:first,second'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->lecturer_id) {
                    $isLecturer = User::where('id', $this->lecturer_id)
                        ->role('lecturer')
                        ->exists();

                    if (! $isLecturer) {
                        $validator->errors()->add(
                            'lecturer_id',
                            'The assigned user is not a lecturer.'
                        );
                    }
                }
                if ($this->course_id && $this->academic_session_id && $this->semester) {
                    $exists = \App\Models\CourseOffering::where('course_id', $this->course_id)
                        ->where('academic_session_id', $this->academic_session_id)
                        ->where('semester', $this->semester)
                        ->exists();

                    if ($exists) {
                        $validator->errors()->add(
                            'course_id',
                            'This course is already offered in this session and semester.'
                        );
                    }
                }
            },
        ];
    }
}
