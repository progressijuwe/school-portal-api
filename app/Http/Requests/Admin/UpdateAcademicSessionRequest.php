<?php

namespace App\Http\Requests\Admin;

use App\Models\AcademicSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class UpdateAcademicSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Fills in whatever the payload left out from the stored row.
     *
     * The date rules compare fields against each other, and `after:` has
     * nothing to compare with when the other side of the pair is absent — so a
     * partial update that moved only `second_semester_start` would have its
     * ordering check quietly skipped, and could be placed before the first
     * semester ends. Merging the current values first means every rule sees a
     * complete picture of what the session would look like after the change.
     */
    protected function prepareForValidation(): void
    {
        /** @var AcademicSession|null $session */
        $session = $this->route('session');

        if ($session === null) {
            return;
        }

        $defaults = [];

        foreach ([
            'start_year',
            'end_year',
            'first_semester_start',
            'first_semester_end',
            'second_semester_start',
            'second_semester_end',
        ] as $field) {
            if (! $this->has($field)) {
                $value = $session->getAttribute($field);

                $defaults[$field] = $value instanceof Carbon
                    ? $value->toDateString()
                    : $value;
            }
        }

        $this->merge($defaults);
    }

    /**
     * `is_current` is absent on purpose — promoting a session is a distinct
     * action with its own endpoint, because it rewrites every other row's flag
     * and changes what the whole portal considers "now".
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $sessionId = $this->route('session')?->id;

        return [
            'name' => [
                'sometimes', 'string', 'max:20',
                Rule::unique('academic_sessions', 'name')->ignore($sessionId),
            ],
            'start_year' => ['sometimes', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'end_year' => ['sometimes', 'integer', 'digits:4', 'gt:start_year', 'max:2100'],

            'first_semester_start' => ['sometimes', 'nullable', 'date'],
            'first_semester_end' => ['sometimes', 'nullable', 'date', 'after:first_semester_start'],
            'second_semester_start' => ['sometimes', 'nullable', 'date', 'after:first_semester_end'],
            'second_semester_end' => ['sometimes', 'nullable', 'date', 'after:second_semester_start'],
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
