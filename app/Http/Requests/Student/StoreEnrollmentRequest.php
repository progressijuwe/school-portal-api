<?php

namespace App\Http\Requests\Student;

use App\Enums\EnrollmentStatus;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

/**
 * Course registration rules, extracted from the controller so they are
 * testable without an HTTP round trip — and so the credit-limit rule is stated
 * exactly once. The controller previously ran the maximum-units check twice
 * with two different error messages.
 */
class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('student') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'course_offering_ids' => ['required', 'array', 'min:1'],
            'course_offering_ids.*' => ['required', 'integer', 'distinct', 'exists:course_offerings,id'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $offerings = CourseOffering::with('course')
                    ->whereIn('id', $this->offeringIds())
                    ->get();

                $this->assertSinglePeriod($validator, $offerings);
                $this->assertNotAlreadyRegistered($validator);
                $this->assertWithinCreditLimits($validator, $offerings);
            },
        ];
    }

    /**
     * @return array<int, int>
     */
    public function offeringIds(): array
    {
        return array_map('intval', $this->input('course_offering_ids', []));
    }

    /**
     * A single registration must not straddle two semesters — the credit limit
     * is a per-semester rule, so a mixed basket cannot be checked coherently.
     *
     * @param  Collection<int, CourseOffering>  $offerings
     */
    private function assertSinglePeriod(Validator $validator, $offerings): void
    {
        $periods = $offerings
            ->map(fn (CourseOffering $offering) => $offering->academic_session_id.'-'.$offering->semester->value)
            ->unique();

        if ($periods->count() > 1) {
            $validator->errors()->add(
                'course_offering_ids',
                'All selected courses must belong to the same academic session and semester.'
            );
        }
    }

    private function assertNotAlreadyRegistered(Validator $validator): void
    {
        $alreadyEnrolled = Enrollment::query()
            ->where('student_id', $this->user()->id)
            ->whereIn('course_offering_id', $this->offeringIds())
            ->occupyingSeat()
            ->exists();

        if ($alreadyEnrolled) {
            $validator->errors()->add(
                'course_offering_ids',
                'You are already registered or pending registration for one or more selected courses.'
            );
        }
    }

    /**
     * @param  Collection<int, CourseOffering>  $offerings
     */
    private function assertWithinCreditLimits(Validator $validator, $offerings): void
    {
        $first = $offerings->first();

        if ($first === null) {
            return;
        }

        $existing = $this->existingCreditUnits($first->academic_session_id, $first->semester->value);
        $incoming = (int) $offerings->sum(fn (CourseOffering $offering) => $offering->course->credit_units);
        $total = $existing + $incoming;

        $max = (int) config('academics.max_credit_units_per_semester');
        $min = (int) config('academics.min_credit_units_per_semester');

        if ($total > $max) {
            $validator->errors()->add(
                'course_offering_ids',
                "This registration would exceed the maximum of {$max} credit units for the semester "
                ."(currently at {$existing}, attempting to add {$incoming})."
            );

            return;
        }

        if ($total < $min) {
            $validator->errors()->add(
                'course_offering_ids',
                "This registration falls below the minimum of {$min} credit units required for the semester "
                ."(currently at {$total})."
            );
        }
    }

    /**
     * Credit units already held for the period, summed in the database.
     *
     * The previous implementation hydrated every enrollment with its offering
     * and course, then summed in PHP — a guaranteed N+1 on the registration
     * page.
     */
    private function existingCreditUnits(int $sessionId, string $semester): int
    {
        return (int) Enrollment::query()
            ->where('enrollments.student_id', $this->user()->id)
            ->whereIn('enrollments.status', EnrollmentStatus::occupyingSeat())
            ->join('course_offerings', 'course_offerings.id', '=', 'enrollments.course_offering_id')
            ->join('courses', 'courses.id', '=', 'course_offerings.course_id')
            ->where('course_offerings.academic_session_id', $sessionId)
            ->where('course_offerings.semester', $semester)
            ->sum('courses.credit_units');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'course_offering_ids.*.distinct' => 'The same course appears more than once in this registration.',
        ];
    }
}
