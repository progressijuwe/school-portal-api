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
}
