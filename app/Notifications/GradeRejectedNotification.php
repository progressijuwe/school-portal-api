<?php

namespace App\Notifications;

use App\Models\Grade;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GradeRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Grade $grade) 
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $course  = $this->grade->enrollment->courseOffering->course;
        $student = $this->grade->enrollment->student;

        return (new MailMessage)
            ->subject('Grade Submission Rejected — ' . $course->title)
            ->greeting("Hello {$notifiable->name},")
            ->line("Your grade submission for **{$student->name}** in **{$course->title}** has been rejected.")
            ->line("**Reason:** {$this->grade->rejection_reason}")
            ->line('Please review and resubmit the grade.')
            ->action('View Grades', url('/lecturer/grades'))
            ->salutation('School Portal Team');
    }

    public function toDatabase(object $notifiable): array
    {
        $course  = $this->grade->enrollment->courseOffering->course;
        $student = $this->grade->enrollment->student;

        return [
            'title'   => 'Grade Submission Rejected',
            'message' => "Your grade submission for {$student->name} in {$course->title} was rejected. Reason: {$this->grade->rejection_reason}",
            'type'    => 'grade_rejected',
            'grade_id'=> $this->grade->id,
        ];
    }
}