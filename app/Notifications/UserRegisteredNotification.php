<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserRegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected string $temporaryPassword,
        protected string $role,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to School Portal — Your Account Details')
            ->greeting("Hello {$notifiable->name},")
            ->line("Your {$this->role} account has been created on the School Portal.")
            ->line('Here are your login credentials:')
            ->line("**Email:** {$notifiable->email}")
            ->line("**Temporary Password:** {$this->temporaryPassword}")
            ->line('You will be required to change your password on first login.')
            ->action('Login to School Portal', url('/login'))
            ->line('If you did not expect this email, please contact the admin immediately.')
            ->salutation('School Portal Team');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Welcome to School Portal',
            'message' => "Your {$this->role} account has been created. Please log in and change your password.",
            'type' => 'account_created',
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
