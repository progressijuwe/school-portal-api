<?php

declare(strict_types=1);

namespace App\Http\Requests\Lecturer\Concerns;

use App\Models\Enrollment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Shared ownership guard for every lecturer grading endpoint.
 *
 * `role:lecturer` middleware establishes *what kind* of user is calling; this
 * establishes *which rows* they may write. Both single and batch submissions
 * route through here so the rule cannot drift between them.
 */
trait ValidatesGradeOwnership
{
    /**
     * @param  array<int, int|string|null>  $enrollmentIds
     */
    protected function assertOwnsEnrollments(Validator $validator, array $enrollmentIds): void
    {
        $ids = array_filter(array_map('intval', array_filter($enrollmentIds, 'is_numeric')));

        if ($ids === [] || $validator->errors()->isNotEmpty()) {
            return;
        }

        $enrollments = Enrollment::with(['courseOffering', 'grade'])
            ->whereIn('id', $ids)
            ->get();

        foreach ($enrollments as $enrollment) {
            $response = Gate::forUser($this->user())->inspect('grade', $enrollment);

            if ($response->denied()) {
                $validator->errors()->add(
                    'grades',
                    $response->message() ?? 'You are not authorized to grade this enrollment.'
                );

                // One clear message beats one per row for a 40-student batch.
                return;
            }
        }
    }
}
