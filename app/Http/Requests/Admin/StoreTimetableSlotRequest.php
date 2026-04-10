<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTimetableSlotRequest extends FormRequest
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
            'course_offering_id' => ['required', 'integer', 'exists:course_offerings,id'],
            'venue_id'           => ['required', 'integer', 'exists:venues,id'],
            'day'                => ['required', 'in:monday,tuesday,wednesday,thursday,friday'],
            'start_time'         => ['required', 'date_format:H:i'],
            'end_time'           => ['required', 'date_format:H:i', 'after:start_time'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $validator->errors()->any()) {
                    $conflicts = app(\App\Services\TimetableService::class)->checkConflicts(
                        $this->course_offering_id,
                        $this->venue_id,
                        $this->day,
                        $this->start_time,
                        $this->end_time,
                    );

                    foreach ($conflicts as $conflict) {
                        $validator->errors()->add('conflict', $conflict);
                    }
                }
            },
        ];
    }
}
