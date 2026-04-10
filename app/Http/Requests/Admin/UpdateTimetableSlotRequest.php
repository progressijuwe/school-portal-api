<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTimetableSlotRequest extends FormRequest
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
            'course_offering_id' => ['sometimes', 'integer', 'exists:course_offerings,id'],
            'venue_id'           => ['sometimes', 'integer', 'exists:venues,id'],
            'day'                => ['sometimes', 'in:monday,tuesday,wednesday,thursday,friday'],
            'start_time'         => ['sometimes', 'date_format:H:i'],
            'end_time'           => ['sometimes', 'date_format:H:i', 'after:start_time'],
            'is_active'          => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $validator->errors()->any()) {
                    $slot = $this->route('slot');

                    $conflicts = app(\App\Services\TimetableService::class)->checkConflicts(
                        $this->course_offering_id ?? $slot->course_offering_id,
                        $this->venue_id           ?? $slot->venue_id,
                        $this->day                ?? $slot->day,
                        $this->start_time         ?? $slot->start_time,
                        $this->end_time           ?? $slot->end_time,
                        $slot->id,
                    );

                    foreach ($conflicts as $conflict) {
                        $validator->errors()->add('conflict', $conflict);
                    }
                }
            },
        ];
    }
}
