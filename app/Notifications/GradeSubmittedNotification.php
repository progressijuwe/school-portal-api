<?php

namespace App\Notifications;

use App\Models\Grade;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class GradeSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Grade  $grade  The first grade in the submission, used for context.
     * @param  int  $count  How many grades the lecturer submitted together, so a
     *                      forty-student mark sheet produces one notification
     *                      rather than forty.
     */
    public function __construct(
        protected Grade $grade,
        protected int $count = 1,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $course = $this->grade->enrollment->courseOffering->course;
        $student = $this->grade->enrollment->student;
        $lecturer = $this->grade->submittedBy;

        $message = $this->count > 1
            ? "{$lecturer?->name} submitted {$this->count} grades for {$course->title}. Please review."
            : "A grade has been submitted for {$student->name} in {$course->title}. Please review.";

        return [
            'title' => $this->count > 1 ? 'Grades Submitted for Approval' : 'Grade Submitted for Approval',
            'message' => $message,
            'type' => 'grade_submitted',
            'grade_id' => $this->grade->id,
            'count' => $this->count,
            'course_code' => $course->code,
        ];
    }
}
