<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\GradeStatus;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GradePolicy
{
    /**
     * Only the lecturer who submitted a grade may amend it, and only while it
     * is not yet approved.
     */
    public function update(User $lecturer, Grade $grade): Response
    {
        if ($grade->status === GradeStatus::Approved) {
            return Response::denyWithStatus(409, 'Cannot update an approved grade.');
        }

        if ($grade->submitted_by !== $lecturer->id) {
            return Response::denyWithStatus(403, 'You are not authorized to update this grade.');
        }

        return Response::allow();
    }

    /**
     * Admins approve or reject. Gate::before already grants admins everything,
     * so this exists to state the state-machine rule rather than the role rule.
     */
    public function review(User $user, Grade $grade): Response
    {
        if ($grade->status === GradeStatus::Approved) {
            return Response::denyWithStatus(409, 'This grade has already been approved.');
        }

        if ($grade->status === GradeStatus::Draft) {
            return Response::denyWithStatus(422, 'This grade is still a draft and has not been submitted for approval.');
        }

        return Response::allow();
    }

    public function view(User $user, Grade $grade): bool
    {
        return $grade->submitted_by === $user->id
            || $grade->enrollment->student_id === $user->id;
    }
}
