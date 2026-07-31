<?php

namespace App\Notifications;

use App\Models\Grade;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GradeApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Grade $grade) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $course = $this->grade->enrollment->courseOffering->course;

        return (new MailMessage)
            ->subject('Grade Released — '.$course->title)
            ->greeting("Hello {$notifiable->name},")
            ->line("Your grade for **{$course->title}** has been released.")
            ->line("**Score:** {$this->grade->score}")
            ->line("**Grade:** {$this->grade->letter_grade}")
            ->line("**Grade Point:** {$this->grade->grade_point}")
            ->action('View Grades', url('/student/grades'))
            ->salutation('School Portal Team');
    }

    public function toDatabase(object $notifiable): array
    {
        $course = $this->grade->enrollment->courseOffering->course;

        return [
            'title' => 'Grade Released',
            'message' => "Your grade for {$course->title} has been released. Grade: {$this->grade->letter_grade}",
            'type' => 'grade_approved',
            'grade_id' => $this->grade->id,
        ];
    }
}
