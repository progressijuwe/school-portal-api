<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Amend an existing offering.
 *
 * Deliberately narrower than the store request: `course_id`,
 * `academic_session_id` and `semester` are not editable. Those three are the
 * offering's identity — they carry the unique constraint, and enrollments,
 * grades and timetable slots all hang off the row. Repointing an offering at a
 * different course after students have registered would silently move their
 * marks onto a course they never took, so a correction there means creating the
 * right offering and deactivating the wrong one.
 */
class UpdateCourseOfferingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lecturer_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->filled('lecturer_id')) {
                    return;
                }

                $isLecturer = User::where('id', $this->integer('lecturer_id'))
                    ->role('lecturer')
                    ->exists();

                if (! $isLecturer) {
                    $validator->errors()->add(
                        'lecturer_id',
                        'The assigned user is not a lecturer.'
                    );
                }
            },
        ];
    }
}
