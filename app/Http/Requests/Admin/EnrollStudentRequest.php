<?php

namespace App\Http\Requests\Admin;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EnrollStudentRequest extends FormRequest
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
            'student_id'          => ['required', 'integer', 'exists:users,id'],
            'course_offering_id'  => ['required', 'integer', 'exists:course_offerings,id'],
        ];
    }
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->student_id) {
                    $isStudent = User::where('id', $this->student_id)
                        ->role('student')
                        ->exists();

                    if (! $isStudent) {
                        $validator->errors()->add(
                            'student_id',
                            'The selected user is not a student.'
                        );
                    }
                }

                if ($this->student_id && $this->course_offering_id) {
                    $alreadyEnrolled = Enrollment::where('student_id', $this->student_id)
                        ->where('course_offering_id', $this->course_offering_id)
                        ->exists();

                    if ($alreadyEnrolled) {
                        $validator->errors()->add(
                            'course_offering_id',
                            'Student is already enrolled in this course.'
                        );
                    }
                }
            },
        ];
    }
}
