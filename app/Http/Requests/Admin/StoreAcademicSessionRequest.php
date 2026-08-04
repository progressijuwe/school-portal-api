<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAcademicSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The four semester dates are required on create, not nullable as the
     * column allows.
     *
     * A session without them is not merely incomplete — `currentSemester()`
     * derives from `second_semester_start`, so a session missing it reports
     * "first semester" forever, and every screen that opens on the current
     * semester would quietly show the wrong half of the year for the whole
     * session. Existing rows may predate the dates, which is why the update
     * request still allows them to be set one at a time.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:20', 'unique:academic_sessions,name'],
            'start_year' => ['required', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'end_year' => ['required', 'integer', 'digits:4', 'gt:start_year', 'max:2100'],

            'first_semester_start' => ['required', 'date'],
            'first_semester_end' => ['required', 'date', 'after:first_semester_start'],
            'second_semester_start' => ['required', 'date', 'after:first_semester_end'],
            'second_semester_end' => ['required', 'date', 'after:second_semester_start'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_year.gt' => 'The end year must be after the start year.',
            'first_semester_end.after' => 'The first semester must end after it starts.',
            'second_semester_start.after' => 'The second semester must start after the first one ends.',
            'second_semester_end.after' => 'The second semester must end after it starts.',
        ];
    }
}
