<?php

namespace App\Notifications;

use App\Models\Grade;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GradeSubmittedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct( protected Grade $grade )
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $course  = $this->grade->enrollment->courseOffering->course;
        $student = $this->grade->enrollment->student;

        return [
            'title'   => 'Grade Submitted for Approval',
            'message' => "A grade has been submitted for {$student->name} in {$course->title}. Please review.",
            'type'    => 'grade_submitted',
            'grade_id'=> $this->grade->id,
        ];
    }

    public function batchSubmit(BatchSubmitGradeRequest $request): JsonResponse
    {
        $lecturerId = $request->user()->id;
        $submitted  = collect();

        foreach ($request->grades as $entry) {
            $grade = $this->persistGrade(
                $entry['enrollment_id'],
                ['ca_score' => $entry['ca_score'], 'project_score' => $entry['project_score'], 'exam_score' => $entry['exam_score']],
                'pending',
                $lecturerId
            );
            $submitted->push($grade);
        }

        $submitted->each->load('enrollment.student', 'enrollment.courseOffering.course', 'submittedBy');

        // Notify admins once per submitted grade (matches the notification's existing single-grade design)
        $admins = User::role('admin')->get();
        foreach ($submitted as $grade) {
            Notification::send($admins, new GradeSubmittedNotification($grade));
        }

        return response()->json([
            'success' => true,
            'message' => "{$submitted->count()} grades submitted successfully and are pending approval.",
            'data'    => GradeResource::collection($submitted),
        ], 201);
    }
}
