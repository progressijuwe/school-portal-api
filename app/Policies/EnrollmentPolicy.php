<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\GradeStatus;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EnrollmentPolicy
{
    /**
     * May this lecturer record a mark against this enrollment?
     *
     * The `role:lecturer` middleware only answers "is this a lecturer" — it says
     * nothing about *which* enrollments they own. Without this check any
     * authenticated lecturer can post an arbitrary enrollment_id and overwrite
     * any student's grade in any course.
     */
    public function grade(User $lecturer, Enrollment $enrollment): Response
    {
        // course_offering_id is NOT NULL with restrictOnDelete, so the
        // relation always resolves.
        $offering = $enrollment->courseOffering;

        if ($offering->lecturer_id !== $lecturer->id) {
            return Response::denyWithStatus(
                403,
                'You can only grade students enrolled in the courses assigned to you.'
            );
        }

        if (! $enrollment->status->isGradable()) {
            return Response::denyWithStatus(
                422,
                'Cannot grade a registration that is not active.'
            );
        }

        if ($enrollment->grade?->status === GradeStatus::Approved) {
            return Response::denyWithStatus(
                409,
                'A grade has already been approved for this enrollment and can no longer be changed.'
            );
        }

        return Response::allow();
    }

    /**
     * A student may only ever see their own registrations.
     */
    public function view(User $user, Enrollment $enrollment): bool
    {
        return $enrollment->student_id === $user->id
            || $enrollment->courseOffering->lecturer_id === $user->id;
    }
}
